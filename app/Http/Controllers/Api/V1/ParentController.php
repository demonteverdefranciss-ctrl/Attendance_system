<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\SubmitExcuseLetterRequest;
use App\Models\AttendanceExcuseRequest;
use App\Models\BiometricPhotoSubmission;
use App\Models\ChildEnrollmentRequest;
use App\Models\Notification;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\BiometricPhotoService;
use App\Services\ExcuseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParentController extends ApiController
{
    public function __construct(
        private AuditService $audit,
        private ExcuseRequestService $excuses,
        private BiometricPhotoService $photos,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $childrenCount = $guardian->students()->count();
        $unreadCount = Notification::where('guardian_id', $guardian->id)->whereNull('read_at')->count();

        return $this->ok([
            'children_count' => $childrenCount,
            'unread_notifications' => $unreadCount,
            'notify_pref' => $guardian->notify_pref ?? 'push',
        ]);
    }

    public function children(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $children = $guardian->students()
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
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'lrn' => $student->lrn,
                    'section' => $student->section
                        ? "{$student->section->grade_level} - {$student->section->name}"
                        : '—',
                    'consent_biometric' => (bool) $student->consent_biometric,
                    'biometric_submission' => $latestSubmission ? [
                        'status' => $latestSubmission->status,
                        'created_at' => $latestSubmission->created_at?->toDateTimeString(),
                        'reviewed_at' => $latestSubmission->reviewed_at?->toDateTimeString(),
                        'notes' => $latestSubmission->notes,
                    ] : null,
                ];
            })
            ->values();

        return $this->ok($children);
    }

    public function storeBiometricPhotos(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'consent_acknowledged' => ['accepted'],
            'photos' => ['required', 'array', 'min:1', 'max:'.BiometricPhotoService::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ]);

        $student = Student::findOrFail($data['student_id']);

        if (! $guardian->students()->where('students.id', $student->id)->exists()) {
            return $this->fail('You can only upload photos for your linked children.', 'FORBIDDEN', 403);
        }

        $pendingExists = BiometricPhotoSubmission::where('student_id', $student->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return $this->fail('A photo submission for this child is already pending teacher review.', 'PENDING_EXISTS', 422);
        }

        $approvedExists = BiometricPhotoSubmission::where('student_id', $student->id)
            ->where('status', 'approved')
            ->whereNull('synced_at')
            ->exists();

        if ($approvedExists) {
            return $this->fail('Approved photos for this child are awaiting import at school.', 'AWAITING_SYNC', 422);
        }

        $submission = $this->photos->createSubmission(
            $student,
            $guardian->id,
            $data['photos'],
            true
        );

        $this->audit->log(
            action: 'biometric_photos_submitted',
            userId: $request->user()->id,
            entity: $submission,
            newValues: [
                'student_id' => $student->id,
                'photo_count' => count($data['photos']),
                'status' => 'pending',
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->ok(['message' => 'Face photos submitted for teacher review.'], 201);
    }

    public function enrollmentRequests(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $items = ChildEnrollmentRequest::where('guardian_id', $guardian->id)
            ->with('student:id,first_name,last_name,lrn')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'lrn' => $r->lrn,
                'student' => $r->full_name,
                'first_name' => $r->first_name,
                'last_name' => $r->last_name,
                'grade_level' => $r->grade_level,
                'relationship' => $r->relationship,
                'status' => $r->status,
                'notes' => $r->notes,
                'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
                'created_at' => $r->created_at?->toDateTimeString(),
            ]);

        return $this->ok($items);
    }

    public function storeEnrollmentRequest(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $data = $request->validate([
            'lrn' => ['required', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $student = Student::where('lrn', $data['lrn'])->first();

        if ($student && $guardian->students()->whereKey($student->id)->exists()) {
            return $this->fail('This child is already linked to your account.', 'ALREADY_LINKED', 422);
        }

        $pendingExists = ChildEnrollmentRequest::where('guardian_id', $guardian->id)
            ->where('lrn', $data['lrn'])
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return $this->fail('An enrollment request for this LRN is already pending.', 'PENDING_EXISTS', 422);
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

        return $this->ok(['message' => 'Enrollment request submitted.'], 201);
    }

    public function updateNotificationPreference(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        $data = $request->validate([
            'notify_pref' => ['required', 'in:push,none'],
        ]);

        $guardian->update(['notify_pref' => $data['notify_pref']]);

        return $this->ok(['notify_pref' => $guardian->notify_pref]);
    }

    public function excuseRequests(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $studentIds = $guardian->students()->pluck('students.id')->all();

        $items = AttendanceExcuseRequest::with('student:id,first_name,last_name,lrn')
            ->whereIn('student_id', $studentIds)
            ->latest('id')
            ->limit(50)
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
                'is_required' => $r->isRequired(),
                'notes' => $r->notes,
                'submitted_at' => $r->submitted_at?->toDateTimeString(),
                'reviewed_at' => $r->reviewed_at?->toDateTimeString(),
                'created_at' => $r->created_at?->toDateTimeString(),
            ]);

        return $this->ok([
            'requests' => $items,
            'eligible_absences' => $this->excuses->eligibleAbsences($guardian),
        ]);
    }

    public function openExcuseRequest(Request $request): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);
        $data = $request->validate([
            'attendance_record_id' => ['required', 'integer', 'exists:attendance_records,id'],
        ]);

        try {
            $opened = $this->excuses->openOptional($guardian, (int) $data['attendance_record_id']);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 'INVALID', 422);
        }

        return $this->ok([
            'message' => 'You can now submit the explanation letter.',
            'id' => $opened->id,
        ], 201);
    }

    public function submitExcuseLetter(SubmitExcuseLetterRequest $request, AttendanceExcuseRequest $excuseRequest): JsonResponse
    {
        $guardian = $this->guardianOrFail($request);

        try {
            $this->excuses->submitLetter(
                $guardian,
                $excuseRequest,
                $request->input('letter_body'),
                $request->file('letter_pdf'),
                $request->file('photo'),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 'INVALID', 422);
        }

        return $this->ok(['message' => 'Explanation letter submitted.']);
    }

    public function excuseLetterFile(Request $request, AttendanceExcuseRequest $excuseRequest, string $type): StreamedResponse
    {
        $guardian = $this->guardianOrFail($request);
        $this->excuses->assertGuardianCanAccess($guardian, $excuseRequest);

        return $this->excuses->attachmentResponse($excuseRequest, $type);
    }

    private function guardianOrFail(Request $request)
    {
        $guardian = $request->user()->guardian;

        if (! $guardian) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->fail('Only guardian accounts can access this resource.', 'FORBIDDEN', 403)
            );
        }

        return $guardian;
    }
}
