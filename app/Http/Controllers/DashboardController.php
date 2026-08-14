<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitExcuseLetterRequest;
use App\Models\AttendanceExcuseRequest;
use App\Models\AttendanceRecord;
use App\Models\BiometricPhotoSubmission;
use App\Models\ChildEnrollmentRequest;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use App\Services\AnalyticsService;
use App\Services\AuditService;
use App\Services\ExcuseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(
        private AnalyticsService $analytics,
        private AuditService $audit,
        private ExcuseRequestService $excuses,
    )
    {
    }

    /**
     * Send the user to the dashboard for their role.
     */
    public function index(): RedirectResponse
    {
        return match (Auth::user()->role?->name) {
            'admin' => redirect()->route('admin.dashboard'),
            'teacher' => redirect()->route('teacher.dashboard'),
            'parent' => redirect()->route('parent.dashboard'),
            default => abort(403, 'No role has been assigned to your account.'),
        };
    }

    public function admin(): Response
    {
        [$from, $to, $trendFrom] = $this->range();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'students' => DB::table('students')->count(),
                'sections' => DB::table('sections')->count(),
                'teachers' => DB::table('teachers')->count(),
                'guardians' => DB::table('guardians')->count(),
            ],
            'summary' => $this->analytics->summary(null, $from, $to),
            'trend' => $this->analytics->dailyTrend(null, $trendFrom, $to),
            'perSection' => $this->analytics->perSection(null, $from, $to),
            'atRisk' => $this->analytics->atRiskStudents(null, $from, $to),
            'methodBreakdown' => $this->analytics->methodBreakdown(null, $from, $to),
            'range' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function teacher(): Response
    {
        [$from, $to, $trendFrom] = $this->range();

        $teacher = DB::table('teachers')->where('user_id', Auth::id())->first();
        $sectionIds = $teacher
            ? DB::table('sections')->where('adviser_id', $teacher->id)->pluck('id')->all()
            : [];

        $scope = $sectionIds ?: [0];

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'sections' => count($sectionIds),
                'students' => $sectionIds
                    ? DB::table('students')->whereIn('section_id', $sectionIds)->count()
                    : 0,
            ],
            'summary' => $this->analytics->summary($scope, $from, $to),
            'trend' => $this->analytics->dailyTrend($scope, $trendFrom, $to),
            'atRisk' => $this->analytics->atRiskStudents($scope, $from, $to),
            'methodBreakdown' => $this->analytics->methodBreakdown($scope, $from, $to),
            'range' => ['from' => $from, 'to' => $to],
            'notifications' => $this->teacherNotificationsPayload($teacher?->id),
        ]);
    }

    public function markTeacherNotificationRead(Request $request, TeacherNotification $teacherNotification): RedirectResponse
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->first();
        abort_unless($teacher && $teacherNotification->teacher_id === $teacher->id, 403);

        if (! $teacherNotification->read_at) {
            $teacherNotification->update(['read_at' => now()]);
        }

        return back();
    }

    public function parent(): Response
    {
        $guardian = Guardian::where('user_id', Auth::id())->first();
        $children = $this->parentChildrenPayload($guardian);
        $notifications = $this->parentNotificationsPayload($guardian, 5);
        $enrollmentRequests = $this->parentEnrollmentPayload($guardian);
        $excuseRequests = $this->parentExcusePayload($guardian);

        $unreadCount = $guardian
            ? Notification::where('guardian_id', $guardian->id)->whereNull('read_at')->count()
            : 0;

        return Inertia::render('Parent/Dashboard', [
            'stats' => ['children' => $children->count()],
            'unreadCount' => $unreadCount,
            'recentNotifications' => $notifications,
            'pendingLetters' => $excuseRequests->where('status', 'awaiting_letter')->count(),
            'pendingEnrollment' => $enrollmentRequests->where('status', 'pending')->count(),
        ]);
    }

    public function parentBiometrics(): Response
    {
        $guardian = Guardian::where('user_id', Auth::id())->first();

        return Inertia::render('Parent/Biometrics', [
            'children' => $this->parentChildrenPayload($guardian),
        ]);
    }

    public function parentEnrollment(): Response
    {
        $guardian = Guardian::where('user_id', Auth::id())->first();

        return Inertia::render('Parent/Enrollment', [
            'enrollmentRequests' => $this->parentEnrollmentPayload($guardian),
        ]);
    }

    public function parentExcuseRequests(): Response
    {
        $guardian = Guardian::where('user_id', Auth::id())->first();

        return Inertia::render('Parent/ExcuseRequests', [
            'excuseRequests' => $this->parentExcusePayload($guardian),
        ]);
    }

    public function parentAttendance(): Response
    {
        $guardian = Guardian::where('user_id', Auth::id())->first();
        $children = $this->parentChildrenPayload($guardian);

        return Inertia::render('Parent/Attendance', [
            'children' => $children,
            'records' => $this->parentAttendancePayload($guardian),
        ]);
    }

    public function parentNotifications(): Response
    {
        $guardian = Guardian::where('user_id', Auth::id())->first();

        return Inertia::render('Parent/Notifications', [
            'notifications' => $this->parentNotificationsPayload($guardian, 50),
            'notifyPref' => $guardian?->notify_pref ?? 'push',
        ]);
    }

    public function submitExcuseLetter(SubmitExcuseLetterRequest $request, AttendanceExcuseRequest $excuseRequest): RedirectResponse
    {
        $guardian = $request->user()->guardian;
        if (! $guardian) {
            abort(403);
        }

        try {
            $this->excuses->submitLetter(
                $guardian,
                $excuseRequest,
                $request->input('letter_body'),
                $request->file('letter_pdf'),
                $request->file('photo'),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('parent.excuse-requests.index')->with('error', $e->getMessage());
        }

        return redirect()->route('parent.excuse-requests.index')
            ->with('success', 'Explanation letter submitted. A teacher will review it.');
    }

    public function excuseLetterFile(Request $request, AttendanceExcuseRequest $excuseRequest, string $type): StreamedResponse
    {
        $guardian = $request->user()->guardian;
        if (! $guardian) {
            abort(403);
        }

        $this->excuses->assertGuardianCanAccess($guardian, $excuseRequest);

        return $this->excuses->attachmentResponse($excuseRequest, $type);
    }

    public function markParentNotificationRead(Request $request, Notification $notification): RedirectResponse
    {
        $guardian = $request->user()->guardian;

        if (! $guardian || $notification->guardian_id !== $guardian->id) {
            abort(403);
        }

        if (! $notification->read_at) {
            $notification->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return redirect()->route('parent.notifications.index')->with('success', 'Notification marked as read.');
    }

    public function updateParentNotificationPreference(Request $request): RedirectResponse
    {
        $guardian = $request->user()->guardian;
        if (! $guardian) {
            abort(403);
        }

        $data = $request->validate([
            'notify_pref' => ['required', 'in:push,none'],
        ]);

        $guardian->update(['notify_pref' => $data['notify_pref']]);

        return redirect()->route('parent.notifications.index')->with('success', 'Notification preference updated.');
    }

    public function createEnrollmentRequest(Request $request): RedirectResponse
    {
        $guardian = $request->user()->guardian;
        if (! $guardian) {
            abort(403);
        }

        $data = $request->validate([
            'lrn' => ['required', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $student = Student::where('lrn', $data['lrn'])->first();

        if ($student && $guardian->students()->where('students.id', $student->id)->exists()) {
            return redirect()->route('parent.enrollment.index')
                ->with('error', 'This child is already linked to your parent account.');
        }

        $pendingExists = ChildEnrollmentRequest::where('guardian_id', $guardian->id)
            ->where('lrn', $data['lrn'])
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return redirect()->route('parent.enrollment.index')
                ->with('error', 'An enrollment request for this LRN is already pending.');
        }

        $enrollmentRequest = ChildEnrollmentRequest::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student?->id,
            'lrn' => $data['lrn'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'] ?? null,
            'grade_level' => $data['grade_level'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'status' => 'pending',
        ]);
        $this->audit->log(
            action: 'child_enrollment_requested',
            userId: $request->user()->id,
            entity: $enrollmentRequest,
            newValues: [
                'student_id' => $student?->id,
                'lrn' => $data['lrn'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'relationship' => $data['relationship'] ?? null,
                'status' => 'pending',
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('parent.enrollment.index')
            ->with('success', 'Child details submitted. A teacher will verify and link your child.');
    }

    public function teacherEnrollmentRequests(Request $request): Response
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $sectionIds = $teacher->sections()->pluck('id')->all();
        $sections = $teacher->sections()
            ->orderBy('name')
            ->get(['id', 'name', 'grade_level'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => "{$s->grade_level} - {$s->name}",
            ]);

        $items = ChildEnrollmentRequest::with([
            'guardian:id,first_name,last_name,phone',
            'student:id,first_name,last_name,lrn,section_id',
            'student.section:id,name,grade_level',
        ])
            ->where('status', 'pending')
            ->where(function ($q) use ($sectionIds) {
                $q->whereNull('student_id')
                    ->orWhereHas('student', fn ($s) => $s->whereIn('section_id', $sectionIds));
            })
            ->latest('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student' => $r->full_name,
                'lrn' => $r->lrn,
                'first_name' => $r->first_name,
                'last_name' => $r->last_name,
                'gender' => $r->gender,
                'grade_level' => $r->grade_level,
                'is_new_student' => $r->student_id === null,
                'section' => $r->student?->section
                    ? "{$r->student->section->grade_level} - {$r->student->section->name}"
                    : ($r->grade_level ? "Requested: {$r->grade_level}" : '—'),
                'guardian' => $r->guardian?->full_name,
                'guardian_phone' => $r->guardian?->phone,
                'relationship' => $r->relationship,
                'created_at' => $r->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Teacher/EnrollmentRequests/Index', [
            'requests' => $items,
            'sections' => $sections,
        ]);
    }

    public function approveEnrollmentRequest(Request $request, ChildEnrollmentRequest $enrollmentRequest): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $this->authorizeEnrollmentRequest($teacher, $enrollmentRequest);

        if ($enrollmentRequest->status !== 'pending') {
            return redirect()->route('teacher.enrollment-requests.index')
                ->with('error', 'This request has already been reviewed.');
        }

        $guardian = $enrollmentRequest->guardian;
        if (! $guardian) {
            return redirect()->route('teacher.enrollment-requests.index')
                ->with('error', 'Request is missing guardian details.');
        }

        $student = $enrollmentRequest->student;

        if (! $student) {
            if (empty($data['section_id'])) {
                return redirect()->route('teacher.enrollment-requests.index')
                    ->with('error', 'Select a section before approving a new student.');
            }

            abort_unless(
                $teacher->sections()->where('sections.id', $data['section_id'])->exists(),
                403
            );

            if (Student::where('lrn', $enrollmentRequest->lrn)->exists()) {
                return redirect()->route('teacher.enrollment-requests.index')
                    ->with('error', 'A student with this LRN already exists.');
            }

            $student = Student::create([
                'section_id' => $data['section_id'],
                'lrn' => $enrollmentRequest->lrn,
                'first_name' => $enrollmentRequest->first_name,
                'last_name' => $enrollmentRequest->last_name,
                'gender' => $enrollmentRequest->gender,
                'consent_biometric' => false,
                'is_active' => true,
            ]);

            $enrollmentRequest->update(['student_id' => $student->id]);
        }

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'relationship' => $enrollmentRequest->relationship,
                'is_primary' => false,
            ],
        ]);

        $enrollmentRequest->update([
            'status' => 'approved',
            'teacher_id' => $teacher->id,
            'reviewed_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->log(
            action: 'child_enrollment_approved',
            userId: $request->user()->id,
            entity: $enrollmentRequest,
            oldValues: ['status' => 'pending'],
            newValues: [
                'status' => 'approved',
                'teacher_id' => $teacher->id,
                'notes' => $data['notes'] ?? null,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('teacher.enrollment-requests.index')
            ->with('success', 'Enrollment request approved.');
    }

    public function rejectEnrollmentRequest(Request $request, ChildEnrollmentRequest $enrollmentRequest): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $this->authorizeEnrollmentRequest($teacher, $enrollmentRequest);

        if ($enrollmentRequest->status !== 'pending') {
            return redirect()->route('teacher.enrollment-requests.index')
                ->with('error', 'This request has already been reviewed.');
        }

        $enrollmentRequest->update([
            'status' => 'rejected',
            'teacher_id' => $teacher->id,
            'reviewed_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->log(
            action: 'child_enrollment_rejected',
            userId: $request->user()->id,
            entity: $enrollmentRequest,
            oldValues: ['status' => 'pending'],
            newValues: [
                'status' => 'rejected',
                'teacher_id' => $teacher->id,
                'notes' => $data['notes'] ?? null,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('teacher.enrollment-requests.index')
            ->with('success', 'Enrollment request rejected.');
    }

    /**
     * @return array{0:string,1:string,2:string}  [from, to, trendFrom]
     */
    private function range(): array
    {
        return [
            now()->subDays(29)->toDateString(),
            now()->toDateString(),
            now()->subDays(13)->toDateString(),
        ];
    }

    private function authorizeEnrollmentRequest(Teacher $teacher, ChildEnrollmentRequest $request): void
    {
        if ($request->student_id === null) {
            return;
        }

        $sectionIds = $teacher->sections()->pluck('id')->all();
        $studentSectionId = $request->student?->section_id;

        abort_unless($studentSectionId && in_array($studentSectionId, $sectionIds, true), 403);
    }

    private function parentChildrenPayload(?Guardian $guardian)
    {
        if (! $guardian) {
            return collect();
        }

        return $guardian->students()
            ->with('section:id,name,grade_level')
            ->orderBy('last_name')
            ->get()
            ->map(function ($student) {
                $latestSubmission = BiometricPhotoSubmission::where('student_id', $student->id)
                    ->latest('id')
                    ->first();

                return [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'lrn' => $student->lrn,
                    'section' => $student->section
                        ? "{$student->section->grade_level} - {$student->section->name}"
                        : '—',
                    'consent_biometric' => $student->consent_biometric,
                    'biometric_submission' => $latestSubmission ? [
                        'status' => $latestSubmission->status,
                        'created_at' => $latestSubmission->created_at?->toDateTimeString(),
                        'reviewed_at' => $latestSubmission->reviewed_at?->toDateTimeString(),
                        'notes' => $latestSubmission->notes,
                    ] : null,
                ];
            })
            ->values();
    }

    private function parentNotificationsPayload(?Guardian $guardian, int $limit = 30)
    {
        if (! $guardian) {
            return collect();
        }

        return Notification::where('guardian_id', $guardian->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'status' => $n->status,
                'sent_at' => $n->sent_at?->toDateTimeString(),
                'read_at' => $n->read_at?->toDateTimeString(),
            ])
            ->values();
    }

    private function parentEnrollmentPayload(?Guardian $guardian)
    {
        if (! $guardian) {
            return collect();
        }

        return ChildEnrollmentRequest::where('guardian_id', $guardian->id)
            ->with('student:id,first_name,last_name,lrn')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'lrn' => $r->lrn,
                'student' => $r->full_name ?: null,
                'first_name' => $r->first_name,
                'last_name' => $r->last_name,
                'grade_level' => $r->grade_level,
                'relationship' => $r->relationship,
                'status' => $r->status,
                'notes' => $r->notes,
                'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
                'created_at' => $r->created_at?->toDateTimeString(),
            ])
            ->values();
    }

    private function parentExcusePayload(?Guardian $guardian)
    {
        if (! $guardian) {
            return collect();
        }

        $studentIds = $guardian->students()->pluck('students.id')->all();
        if ($studentIds === []) {
            return collect();
        }

        return AttendanceExcuseRequest::with('student:id,first_name,last_name,lrn')
            ->whereIn('student_id', $studentIds)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student' => $r->student?->full_name,
                'student_id' => $r->student_id,
                'streak_count' => $r->streak_count,
                'streak_summary' => $r->streak_summary,
                'status' => $r->status,
                'letter_body' => $r->letter_body,
                ...$r->attachmentMeta(),
                'notes' => $r->notes,
                'notified_at' => $r->notified_at?->toDateTimeString(),
                'submitted_at' => $r->submitted_at?->toDateTimeString(),
                'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
                'created_at' => $r->created_at?->toDateTimeString(),
            ])
            ->values();
    }

    private function parentAttendancePayload(?Guardian $guardian)
    {
        if (! $guardian) {
            return collect();
        }

        $studentIds = $guardian->students()->pluck('students.id')->all();
        if ($studentIds === []) {
            return collect();
        }

        return AttendanceRecord::with([
            'student:id,first_name,last_name',
            'session:id,session_date,section_id',
            'session.section:id,name,grade_level',
        ])
            ->whereIn('student_id', $studentIds)
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student_id' => $r->student_id,
                'student' => $r->student?->full_name,
                'date' => $r->session?->session_date?->toDateString(),
                'section' => $r->session?->section
                    ? "{$r->session->section->grade_level} - {$r->session->section->name}"
                    : '—',
                'status' => $r->status,
                'time_in' => $r->time_in?->toDateTimeString(),
                'time_out' => $r->time_out?->toDateTimeString(),
                'method' => $r->method,
            ])
            ->values();
    }

    private function teacherNotificationsPayload(?int $teacherId)
    {
        if (! $teacherId) {
            return collect();
        }

        try {
            return TeacherNotification::where('teacher_id', $teacherId)
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'body' => $n->body,
                    'read_at' => $n->read_at?->toDateTimeString(),
                    'created_at' => $n->created_at?->toDateTimeString(),
                ])
                ->values();
        } catch (\Throwable) {
            return collect();
        }
    }
}
