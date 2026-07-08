<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\ChildEnrollmentRequest;
use App\Models\Notification;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentController extends ApiController
{
    public function __construct(private AuditService $audit)
    {
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
