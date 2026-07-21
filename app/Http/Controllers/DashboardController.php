<?php

namespace App\Http\Controllers;

use App\Models\BiometricPhotoSubmission;
use App\Models\ChildEnrollmentRequest;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AnalyticsService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private AnalyticsService $analytics,
        private AuditService $audit
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
        ]);
    }

    public function parent(): Response
    {
        $guardian = Guardian::where('user_id', Auth::id())->first();

        $notifications = $guardian
            ? Notification::where('guardian_id', $guardian->id)
                ->latest('id')
                ->limit(30)
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
                ->values()
            : collect();

        $unreadCount = $guardian
            ? Notification::where('guardian_id', $guardian->id)->whereNull('read_at')->count()
            : 0;
        $enrollmentRequests = $guardian
            ? ChildEnrollmentRequest::where('guardian_id', $guardian->id)
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
                ->values()
            : collect();

        $children = $guardian
            ? $guardian->students()
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
                ->values()
            : collect();

        return Inertia::render('Parent/Dashboard', [
            'stats' => ['children' => $children->count()],
            'children' => $children,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'notifyPref' => $guardian?->notify_pref ?? 'push',
            'enrollmentRequests' => $enrollmentRequests,
        ]);
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

        return redirect()->route('parent.dashboard')->with('success', 'Notification marked as read.');
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

        return redirect()->route('parent.dashboard')->with('success', 'Notification preference updated.');
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
            return redirect()->route('parent.dashboard')
                ->with('error', 'This child is already linked to your parent account.');
        }

        $pendingExists = ChildEnrollmentRequest::where('guardian_id', $guardian->id)
            ->where('lrn', $data['lrn'])
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return redirect()->route('parent.dashboard')
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

        return redirect()->route('parent.dashboard')
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
}
