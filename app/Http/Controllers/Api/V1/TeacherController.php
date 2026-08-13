<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceExcuseRequest;
use App\Models\AttendanceSession;
use App\Models\BiometricPhoto;
use App\Models\BiometricPhotoSubmission;
use App\Models\ChildEnrollmentRequest;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AnalyticsService;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Services\BiometricPhotoService;
use App\Services\ExcuseRequestService;
use App\Services\RecognitionProcessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherController extends ApiController
{
    public function __construct(
        private AnalyticsService $analytics,
        private AttendanceService $attendance,
        private AuditService $audit,
        private BiometricPhotoService $photos,
        private RecognitionProcessService $recognition,
        private ExcuseRequestService $excuses,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $sectionIds = $teacher->sections()->pluck('id')->all();
        $scope = $sectionIds ?: [0];

        $from = now()->subDays(29)->toDateString();
        $to = now()->toDateString();

        $pendingEnrollment = ChildEnrollmentRequest::where('status', 'pending')
            ->where(function ($q) use ($sectionIds) {
                $q->whereNull('student_id')
                    ->orWhereHas('student', fn ($s) => $s->whereIn('section_id', $sectionIds));
            })
            ->count();

        $pendingBiometric = BiometricPhotoSubmission::where('status', 'pending')
            ->whereHas('student', fn ($q) => $q->whereIn('section_id', $sectionIds))
            ->count();

        $openSessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'open')
            ->whereDate('session_date', now()->toDateString())
            ->count();

        return $this->ok([
            'sections_count' => count($sectionIds),
            'students_count' => $sectionIds
                ? Student::whereIn('section_id', $sectionIds)->where('is_active', true)->count()
                : 0,
            'open_sessions' => $openSessions,
            'pending_enrollment' => $pendingEnrollment,
            'pending_biometric' => $pendingBiometric,
            'summary' => $this->analytics->summary($scope, $from, $to),
            'range' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function attendanceIndex(Request $request): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $sectionIds = $teacher->sections()->pluck('id')->all();
        $today = now()->toDateString();

        $sections = Section::whereIn('id', $sectionIds)
            ->withCount(['students' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $sessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->whereDate('session_date', $today)
            ->withCount([
                'records as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late']),
                'records as absent_count' => fn ($q) => $q->where('status', 'absent'),
            ])
            ->orderByDesc('opened_at')
            ->get()
            ->groupBy('section_id')
            ->map(fn ($group) => $group->firstWhere('status', 'open')
                ?? $group->sortByDesc('opened_at')->first());

        $rows = $sections->map(function ($section) use ($sessions) {
            $session = $sessions->get($section->id);

            return [
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'grade_level' => $section->grade_level,
                    'students_count' => $section->students_count,
                ],
                'session' => $session ? [
                    'id' => $session->id,
                    'status' => $session->status,
                    'present_count' => $session->present_count,
                    'absent_count' => $session->absent_count,
                    'opened_at' => $session->opened_at?->toDateTimeString(),
                ] : null,
            ];
        });

        return $this->ok([
            'today' => $today,
            'rows' => $rows,
            'recognition' => $this->recognition->snapshot(),
        ]);
    }

    public function openSession(Request $request): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $data = $request->validate(['section_id' => ['required', 'integer']]);
        $sectionId = (int) $data['section_id'];

        $sectionIds = $teacher->sections()->pluck('id')->all();
        if (! in_array($sectionId, $sectionIds, true)) {
            return $this->fail('You cannot open a session for this section.', 'FORBIDDEN', 403);
        }

        $section = Section::findOrFail($sectionId);
        $session = $this->attendance->openSession($section, now());
        $recognitionStarted = $this->recognition->ensureRunning();

        return $this->ok([
            'session_id' => $session->id,
            'recognition_started' => $recognitionStarted,
            'recognition' => $this->recognition->snapshot(),
            'message' => $recognitionStarted || ! $this->recognition->isEnabled()
                ? 'Attendance session opened.'
                : 'Session opened, but face recognition could not be started automatically.',
        ], 201);
    }

    public function showSession(Request $request, AttendanceSession $session): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $this->authorizeSession($teacher, $session);

        $session->load('section:id,name,grade_level');

        $students = Student::where('section_id', $session->section_id)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $records = $session->records()
            ->get(['student_id', 'status', 'time_in', 'time_out', 'method'])
            ->mapWithKeys(fn ($r) => [
                $r->student_id => [
                    'status' => $r->status,
                    'time_in' => $r->time_in?->toDateTimeString(),
                    'time_out' => $r->time_out?->toDateTimeString(),
                    'method' => $r->method,
                ],
            ]);

        return $this->ok([
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'session_date' => $session->session_date?->toDateString(),
                'section' => $session->section?->name,
                'grade_level' => $session->section?->grade_level,
            ],
            'students' => $students,
            'records' => $records,
            'recognition' => $this->recognition->snapshot(),
        ]);
    }

    public function storeAttendance(Request $request, AttendanceSession $session): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $this->authorizeSession($teacher, $session);

        $data = $request->validate([
            'records' => ['required', 'array'],
            'records.*' => ['in:present,late,absent,excused'],
        ]);

        $validStudentIds = Student::where('section_id', $session->section_id)
            ->pluck('id')
            ->all();

        foreach ($data['records'] as $studentId => $status) {
            if (in_array((int) $studentId, $validStudentIds, true)) {
                $this->attendance->mark($session, (int) $studentId, $status, [
                    'marked_by' => $request->user()->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        return $this->ok(['message' => 'Attendance saved.']);
    }

    public function closeSession(Request $request, AttendanceSession $session): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $this->authorizeSession($teacher, $session);

        $this->attendance->closeSession($session);

        return $this->ok(['message' => 'Session closed. Unmarked students were recorded absent.']);
    }

    public function recordTimeOut(Request $request, AttendanceSession $session, Student $student): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $this->authorizeSession($teacher, $session);

        if ($session->status === 'closed') {
            return $this->fail('Cannot record time-out on a closed session.', 'SESSION_CLOSED', 422);
        }

        if ($student->section_id !== $session->section_id) {
            return $this->fail('Student is not in this session section.', 'FORBIDDEN', 403);
        }

        try {
            $this->attendance->recordTimeOut($session, $student->id, now(), [
                'marked_by' => $request->user()->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 'INVALID_TIMEOUT', 422);
        }

        return $this->ok(['message' => 'Time-out recorded.']);
    }

    public function enrollmentRequests(Request $request): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
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

        return $this->ok([
            'requests' => $items,
            'sections' => $sections,
        ]);
    }

    public function approveEnrollmentRequest(Request $request, ChildEnrollmentRequest $enrollmentRequest): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
        ]);

        $this->authorizeEnrollmentRequest($teacher, $enrollmentRequest);

        if ($enrollmentRequest->status !== 'pending') {
            return $this->fail('This request has already been reviewed.', 'ALREADY_REVIEWED', 422);
        }

        $guardian = $enrollmentRequest->guardian;
        if (! $guardian) {
            return $this->fail('Request is missing guardian details.', 'MISSING_GUARDIAN', 422);
        }

        $student = $enrollmentRequest->student;

        if (! $student) {
            if (empty($data['section_id'])) {
                return $this->fail('Select a section before approving a new student.', 'SECTION_REQUIRED', 422);
            }

            if (! $teacher->sections()->where('sections.id', $data['section_id'])->exists()) {
                return $this->fail('You cannot assign students to this section.', 'FORBIDDEN', 403);
            }

            if (Student::where('lrn', $enrollmentRequest->lrn)->exists()) {
                return $this->fail('A student with this LRN already exists.', 'LRN_EXISTS', 422);
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

        return $this->ok(['message' => 'Enrollment request approved.']);
    }

    public function rejectEnrollmentRequest(Request $request, ChildEnrollmentRequest $enrollmentRequest): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $this->authorizeEnrollmentRequest($teacher, $enrollmentRequest);

        if ($enrollmentRequest->status !== 'pending') {
            return $this->fail('This request has already been reviewed.', 'ALREADY_REVIEWED', 422);
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

        return $this->ok(['message' => 'Enrollment request rejected.']);
    }

    public function biometricSubmissions(Request $request): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $sectionIds = $teacher->sections()->pluck('id')->all();

        $items = BiometricPhotoSubmission::with([
            'student:id,first_name,last_name,lrn,section_id',
            'student.section:id,name,grade_level',
            'guardian:id,first_name,last_name,phone',
            'photos:id,submission_id,original_name,sort_order',
        ])
            ->where('status', 'pending')
            ->whereHas('student', fn ($q) => $q->whereIn('section_id', $sectionIds))
            ->latest('id')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'student' => $s->student?->full_name,
                'lrn' => $s->student?->lrn,
                'section' => $s->student?->section
                    ? "{$s->student->section->grade_level} - {$s->student->section->name}"
                    : '—',
                'guardian' => $s->guardian?->full_name,
                'guardian_phone' => $s->guardian?->phone,
                'photo_count' => $s->photos->count(),
                'photos' => $s->photos->map(fn ($p) => [
                    'id' => $p->id,
                    'url' => url("/api/v1/teacher/biometric-photos/{$p->id}/file"),
                    'name' => $p->original_name,
                ]),
                'created_at' => $s->created_at?->toDateTimeString(),
            ]);

        return $this->ok($items);
    }

    public function approveBiometricSubmission(Request $request, BiometricPhotoSubmission $submission): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $this->authorizeBiometricSubmission($teacher, $submission);

        if ($submission->status !== 'pending') {
            return $this->fail('This submission has already been reviewed.', 'ALREADY_REVIEWED', 422);
        }

        $this->photos->approve($submission, $teacher, $data['notes'] ?? null);

        $this->audit->log(
            action: 'biometric_photos_approved',
            userId: $request->user()->id,
            entity: $submission,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'approved', 'teacher_id' => $teacher->id],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->ok(['message' => 'Photos approved. Biometric consent recorded.']);
    }

    public function rejectBiometricSubmission(Request $request, BiometricPhotoSubmission $submission): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $this->authorizeBiometricSubmission($teacher, $submission);

        if ($submission->status !== 'pending') {
            return $this->fail('This submission has already been reviewed.', 'ALREADY_REVIEWED', 422);
        }

        $this->photos->reject($submission, $teacher, $data['notes'] ?? null);

        $this->audit->log(
            action: 'biometric_photos_rejected',
            userId: $request->user()->id,
            entity: $submission,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'rejected', 'teacher_id' => $teacher->id],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->ok(['message' => 'Photo submission rejected.']);
    }

    public function excuseRequests(Request $request): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $sectionIds = $teacher->sections()->pluck('id')->all();

        $items = AttendanceExcuseRequest::with([
            'student:id,first_name,last_name,lrn,section_id',
            'student.section:id,name,grade_level',
            'guardian:id,first_name,last_name,phone',
        ])
            ->where('status', 'pending')
            ->whereHas('student', fn ($q) => $q->whereIn('section_id', $sectionIds))
            ->latest('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student' => $r->student?->full_name,
                'lrn' => $r->student?->lrn,
                'section' => $r->student?->section
                    ? "{$r->student->section->grade_level} - {$r->student->section->name}"
                    : '—',
                'guardian' => $r->guardian?->full_name,
                'streak_count' => $r->streak_count,
                'streak_summary' => $r->streak_summary,
                'letter_body' => $r->letter_body,
                ...$r->attachmentMeta(),
                'submitted_at' => $r->submitted_at?->toDateTimeString(),
            ]);

        return $this->ok($items);
    }

    public function approveExcuseRequest(Request $request, AttendanceExcuseRequest $excuseRequest): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        try {
            $this->excuses->approve($teacher, $excuseRequest, $data['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 'INVALID', 422);
        }

        return $this->ok(['message' => 'Explanation approved. Records marked excused.']);
    }

    public function rejectExcuseRequest(Request $request, AttendanceExcuseRequest $excuseRequest): JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        try {
            $this->excuses->reject($teacher, $excuseRequest, $data['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 'INVALID', 422);
        }

        return $this->ok(['message' => 'Explanation letter rejected.']);
    }

    public function excuseLetterFile(Request $request, AttendanceExcuseRequest $excuseRequest, string $type): StreamedResponse|JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $this->excuses->assertTeacherCanAccess($teacher, $excuseRequest);

        return $this->excuses->attachmentResponse($excuseRequest, $type);
    }

    public function biometricPhotoFile(Request $request, BiometricPhoto $photo): StreamedResponse|JsonResponse
    {
        $teacher = $this->teacherOrFail($request);
        $submission = $photo->submission()->with('student')->firstOrFail();
        $this->authorizeBiometricSubmission($teacher, $submission);

        if (! Storage::disk('local')->exists($photo->storage_path)) {
            return $this->fail('Photo file not found.', 'NOT_FOUND', 404);
        }

        return Storage::disk('local')->response($photo->storage_path);
    }

    private function teacherOrFail(Request $request): Teacher
    {
        if (! $request->user()->hasRole('teacher')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->fail('Only teacher accounts can access this resource.', 'FORBIDDEN', 403)
            );
        }

        $teacher = $request->user()->teacher;
        if (! $teacher) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->fail('Teacher profile not found.', 'NOT_FOUND', 404)
            );
        }

        return $teacher;
    }

    private function authorizeSession(Teacher $teacher, AttendanceSession $session): void
    {
        $sectionIds = $teacher->sections()->pluck('id')->all();
        if (! in_array($session->section_id, $sectionIds, true)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->fail('You cannot access this session.', 'FORBIDDEN', 403)
            );
        }
    }

    private function authorizeEnrollmentRequest(Teacher $teacher, ChildEnrollmentRequest $enrollmentRequest): void
    {
        if ($enrollmentRequest->student_id === null) {
            return;
        }

        $sectionIds = $teacher->sections()->pluck('id')->all();
        $studentSectionId = $enrollmentRequest->student?->section_id;

        if (! $studentSectionId || ! in_array($studentSectionId, $sectionIds, true)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->fail('You cannot review this enrollment request.', 'FORBIDDEN', 403)
            );
        }
    }

    private function authorizeBiometricSubmission(Teacher $teacher, BiometricPhotoSubmission $submission): void
    {
        $sectionIds = $teacher->sections()->pluck('id')->all();
        $studentSectionId = $submission->student?->section_id;

        if (! $studentSectionId || ! in_array($studentSectionId, $sectionIds, true)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->fail('You cannot review this submission.', 'FORBIDDEN', 403)
            );
        }
    }
}
