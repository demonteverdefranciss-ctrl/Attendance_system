<?php

namespace App\Http\Controllers;

use App\Models\ChildEnrollmentRequest;
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

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'sections' => count($sectionIds),
                'students' => $sectionIds
                    ? DB::table('students')->whereIn('section_id', $sectionIds)->count()
                    : 0,
            ],
            'summary' => $this->analytics->summary($sectionIds ?: [0], $from, $to),
            'trend' => $this->analytics->dailyTrend($sectionIds ?: [0], $trendFrom, $to),
            'range' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function parent(): Response
    {
        $guardian = DB::table('guardians')->where('user_id', Auth::id())->first();
        $children = $guardian
            ? DB::table('student_guardian')->where('guardian_id', $guardian->id)->count()
            : 0;

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
                    'student' => $r->student ? $r->student->full_name : null,
                    'relationship' => $r->relationship,
                    'status' => $r->status,
                    'notes' => $r->notes,
                    'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
                    'created_at' => $r->created_at?->toDateTimeString(),
                ])
                ->values()
            : collect();

        return Inertia::render('Parent/Dashboard', [
            'stats' => ['children' => $children],
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
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $student = Student::where('lrn', $data['lrn'])->first();
        if (! $student) {
            return redirect()->route('parent.dashboard')
                ->with('error', 'No student found for the provided LRN.');
        }

        if ($guardian->students()->where('students.id', $student->id)->exists()) {
            return redirect()->route('parent.dashboard')
                ->with('error', 'This child is already linked to your parent account.');
        }

        $pendingExists = ChildEnrollmentRequest::where('guardian_id', $guardian->id)
            ->where('student_id', $student->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return redirect()->route('parent.dashboard')
                ->with('error', 'An enrollment request for this child is already pending.');
        }

        $enrollmentRequest = ChildEnrollmentRequest::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'lrn' => $student->lrn,
            'relationship' => $data['relationship'] ?? null,
            'status' => 'pending',
        ]);
        $this->audit->log(
            action: 'child_enrollment_requested',
            userId: $request->user()->id,
            entity: $enrollmentRequest,
            newValues: [
                'student_id' => $student->id,
                'lrn' => $student->lrn,
                'relationship' => $data['relationship'] ?? null,
                'status' => 'pending',
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('parent.dashboard')
            ->with('success', 'Enrollment request submitted and pending teacher verification.');
    }

    public function teacherEnrollmentRequests(Request $request): Response
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $sectionIds = $teacher->sections()->pluck('id')->all();

        $items = ChildEnrollmentRequest::with([
            'guardian:id,first_name,last_name,phone',
            'student:id,first_name,last_name,lrn,section_id',
            'student.section:id,name,grade_level',
        ])
            ->whereHas('student', fn ($q) => $q->whereIn('section_id', $sectionIds))
            ->where('status', 'pending')
            ->latest('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student' => $r->student?->full_name,
                'lrn' => $r->lrn,
                'section' => $r->student?->section ? "{$r->student->section->grade_level} - {$r->student->section->name}" : '—',
                'guardian' => $r->guardian?->full_name,
                'guardian_phone' => $r->guardian?->phone,
                'relationship' => $r->relationship,
                'created_at' => $r->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Teacher/EnrollmentRequests/Index', [
            'requests' => $items,
        ]);
    }

    public function approveEnrollmentRequest(Request $request, ChildEnrollmentRequest $enrollmentRequest): RedirectResponse
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

        $student = $enrollmentRequest->student;
        $guardian = $enrollmentRequest->guardian;
        if (! $student || ! $guardian) {
            return redirect()->route('teacher.enrollment-requests.index')
                ->with('error', 'Request is missing student/guardian details.');
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
        $sectionIds = $teacher->sections()->pluck('id')->all();
        $studentSectionId = $request->student?->section_id;

        abort_unless($studentSectionId && in_array($studentSectionId, $sectionIds, true), 403);
    }
}
