<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Camera;
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
     * Auto-detects arrival (time_in) vs departure (time_out).
     */
    public function recognitions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'captured_at' => ['nullable', 'date'],
            'client_uuid' => ['required', 'uuid'],
            'session_id' => ['nullable', 'integer'],
            'event_type' => ['nullable', 'in:in,out'],
        ]);
        return $this->ok(app(\App\Services\RecognitionIngestService::class)
            ->ingest($request->attributes->get('camera'), $data));
    }

    /**
     * Whether this camera should run (device-authenticated).
     * Dedicated cameras turn on only when one of their assigned sections is open.
     * Unassigned cameras still follow any open session.
     */
    public function openSessionsForDevice(Request $request): JsonResponse
    {
        // Do not filter by session_date only — timezone skew between Railway
        // and the device can hide a session that the website shows as open.
        $query = AttendanceSession::where('status', 'open');

        $camera = $request->attributes->get('camera');
        if ($camera instanceof Camera) {
            $sectionIds = $camera->sections()->pluck('id');
            if ($sectionIds->isNotEmpty()) {
                $query->whereIn('section_id', $sectionIds);
            } else {
                $query->whereHas('section', fn ($q) => $q->whereNull('camera_id'));
            }
        }

        $sessions = $query->with(['schedule', 'section'])->get()->filter(fn ($session) =>
            $session->opened_at && \App\Services\RecognitionIngestService::expiresAt($session)->isFuture());
        $count = $sessions->count();

        return $this->ok([
            'open' => $count > 0,
            'count' => $count,
            'sessions' => $sessions->map(fn ($session) => [
                'id' => $session->id,
                'section_id' => $session->section_id,
                'opened_at' => $session->opened_at->toIso8601String(),
                'expected_close_at' => \App\Services\RecognitionIngestService::expiresAt($session)->toIso8601String(),
                'student_ids' => Student::where('section_id', $session->section_id)
                    ->where('is_active', true)->where('consent_biometric', true)->pluck('id')->all(),
            ])->values(),
            'engine' => \App\Support\RecognitionEngine::current(),
        ]);
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
            'time_out' => $r->time_out?->toDateTimeString(),
            'method' => $r->method,
            'confidence' => $r->confidence,
        ];
    }
}
