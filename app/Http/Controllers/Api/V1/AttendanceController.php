<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends ApiController
{
    public function __construct(private AttendanceService $attendance)
    {
    }

    /**
     * Ingest a recognition event from the camera node (device-authenticated).
     * Idempotent via client_uuid so offline buffers can re-send safely.
     */
    public function recognitions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'captured_at' => ['nullable', 'date'],
            'client_uuid' => ['required', 'uuid'],
        ]);

        // Idempotency: a re-sent event returns the existing record unchanged.
        $existing = AttendanceRecord::where('client_uuid', $data['client_uuid'])->first();
        if ($existing) {
            return $this->ok($this->recordPayload($existing), 200);
        }

        $student = Student::find($data['student_id']);

        if (! $student->section_id) {
            return $this->fail('Student is not assigned to a section.', 'NO_SECTION', 422);
        }

        $session = $this->attendance->currentOpenSession($student->section_id);

        if (! $session) {
            return $this->fail('No active attendance session for this section.', 'NO_SESSION', 422);
        }

        $capturedAt = isset($data['captured_at']) ? Carbon::parse($data['captured_at']) : now();
        $status = $this->attendance->statusForArrival($session->schedule, $capturedAt);
        $camera = $request->attributes->get('camera');

        $record = $this->attendance->mark($session, $student->id, $status, [
            'method' => 'face',
            'confidence' => $data['confidence'] ?? null,
            'camera_id' => $camera?->id,
            'time_in' => $capturedAt,
            'client_uuid' => $data['client_uuid'],
        ]);

        return $this->ok($this->recordPayload($record), 201);
    }

    /**
     * Open sessions today (teacher → own sections, admin → all).
     */
    public function activeSessions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole(['teacher', 'admin'])) {
            return $this->fail('Forbidden.', 'FORBIDDEN', 403);
        }

        $query = AttendanceSession::with('section:id,name')
            ->where('status', 'open')
            ->whereDate('session_date', now()->toDateString());

        if ($user->hasRole('teacher')) {
            $sectionIds = $user->teacher ? $user->teacher->sections()->pluck('id') : collect();
            $query->whereIn('section_id', $sectionIds);
        }

        return $this->ok($query->get()->map(fn ($s) => [
            'id' => $s->id,
            'section' => $s->section?->name,
            'date' => $s->session_date?->toDateString(),
            'status' => $s->status,
        ]));
    }

    /**
     * Attendance records filtered by section/date (teacher → own sections).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole(['teacher', 'admin'])) {
            return $this->fail('Forbidden.', 'FORBIDDEN', 403);
        }

        $data = $request->validate([
            'section_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ]);

        $query = AttendanceRecord::with(['student:id,first_name,last_name', 'session:id,section_id,session_date']);

        if (! empty($data['section_id'])) {
            $query->whereHas('session', fn ($q) => $q->where('section_id', $data['section_id']));
        }

        if (! empty($data['date'])) {
            $query->whereHas('session', fn ($q) => $q->whereDate('session_date', $data['date']));
        }

        if ($user->hasRole('teacher')) {
            $sectionIds = $user->teacher ? $user->teacher->sections()->pluck('id') : collect();
            $query->whereHas('session', fn ($q) => $q->whereIn('section_id', $sectionIds));
        }

        return $this->ok(
            $query->latest('id')->limit(200)->get()->map(fn ($r) => $this->recordPayload($r))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(AttendanceRecord $r): array
    {
        return [
            'id' => $r->id,
            'student_id' => $r->student_id,
            'session_id' => $r->session_id,
            'status' => $r->status,
            'time_in' => $r->time_in?->toDateTimeString(),
            'method' => $r->method,
            'confidence' => $r->confidence,
        ];
    }
}
