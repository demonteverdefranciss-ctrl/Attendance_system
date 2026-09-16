<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Camera;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecognitionIngestService
{
    public function __construct(private AttendanceService $attendance) {}

    public static function expiresAt(AttendanceSession $session): Carbon
    {
        // Even when automatic closing is disabled, offline authorization is bounded.
        $minutes = $session->section?->sessionMaxMinutes()
            ?: (int) config('attendance.session_max_minutes', 360);
        $expires = $session->opened_at->copy()->addMinutes($minutes > 0 ? $minutes : 360);
        if ($session->schedule?->end_time) {
            $end = $session->session_date->copy()->setTimeFromTimeString($session->schedule->end_time);
            if ($end->gt($session->opened_at) && $end->lt($expires)) {
                $expires = $end;
            }
        }
        return $expires;
    }

    public function ingest(Camera $camera, array $data): array
    {
        return DB::transaction(function () use ($camera, $data) {
            // Serialize uploads from this device, including duplicate concurrent requests.
            Camera::whereKey($camera->id)->lockForUpdate()->firstOrFail();
            $receipt = DB::table('recognition_events')->where('client_uuid', $data['client_uuid'])->first();
            if ($receipt) {
                abort_unless($receipt->camera_id == $camera->id && $receipt->student_id == $data['student_id'], 409, 'Event ID belongs to another capture.');
                abort_if((isset($data['session_id']) && $receipt->session_id != $data['session_id'])
                    || (isset($data['event_type']) && $receipt->event_type !== $data['event_type'])
                    || (isset($data['captured_at']) && Carbon::parse($data['captured_at'])->getTimestamp() !== Carbon::parse($receipt->captured_at)->getTimestamp()),
                    409, 'Event ID was reused with different capture details.');
                return json_decode($receipt->response, true);
            }
            // Preserve replay protection for captures accepted before this upgrade.
            $legacy = AttendanceRecord::where('client_uuid', $data['client_uuid'])->first();
            if ($legacy) {
                abort_unless($legacy->camera_id == $camera->id && $legacy->student_id == $data['student_id'], 409);
                return $legacy->toArray();
            }

            $captured = isset($data['captured_at']) ? Carbon::parse($data['captured_at'])->setTimezone(config('app.timezone')) : now();
            abort_if($captured->gt(now()->addMinutes(2)) || $captured->lt(now()->subDays(7)), 422, 'Capture time is outside the accepted seven-day window.');
            $student = Student::with('section')->findOrFail($data['student_id']);
            abort_unless($student->is_active && $student->consent_biometric && $student->section_id, 422, 'Student is not eligible for face attendance.');
            abort_unless($camera->coversSection($student->section_id, $student->section?->camera_id), 403, 'Camera does not cover this section.');

            $query = AttendanceSession::with('schedule')->where('section_id', $student->section_id);
            if (!empty($data['session_id'])) {
                $query->whereKey($data['session_id']);
            } else {
                $query->where('opened_at', '<=', $captured)
                    ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $captured));
            }
            $session = $query->latest('opened_at')->lockForUpdate()->first();
            abort_unless($session && $session->opened_at, 422, 'No authorized session for this capture.');
            abort_if($captured->lt($session->opened_at) || $captured->gte(self::expiresAt($session))
                || ($session->closed_at && $captured->gt($session->closed_at)), 422, 'Capture is outside the attendance session.');

            $record = AttendanceRecord::where('session_id', $session->id)->where('student_id', $student->id)->lockForUpdate()->first();
            $type = $data['event_type'] ?? (($record?->time_in && !$record?->time_out) ? 'out' : 'in');
            $delayed = $captured->lt(now()->subMinute());
            if ($type === 'out') {
                abort_unless($record?->time_in && $captured->gte($record->time_in), 422, 'Departure requires an earlier arrival.');
                // A closed session may already contain an automatic departure. Never overwrite teacher edits.
                if ($record->time_out && $record->time_out->equalTo($session->closed_at ?? now()) && !$record->marked_by) {
                    $record->update(['time_out' => null]);
                }
                $record = $this->attendance->recordTimeOut($session, $student->id, $captured);
            } elseif (!$record?->time_in) {
                abort_if($record && ($record->marked_by || $record->status === 'excused'), 409, 'Attendance was reviewed by a teacher.');
                $record = $this->attendance->mark($session, $student->id,
                    $this->attendance->statusForArrival($session->schedule, $captured, $session), [
                        'time_in' => $captured, 'method' => 'face', 'camera_id' => $camera->id,
                        'confidence' => $data['confidence'] ?? null, 'was_offline' => $delayed,
                    ]);
                // Match normal close-session behavior for an arrival uploaded after closing.
                if ($session->closed_at) {
                    $record = $this->attendance->recordTimeOut($session, $student->id, $session->closed_at);
                }
            }
            $response = $record->fresh()->toArray();
            $response['was_offline'] = $delayed;
            $response['captured_at'] = $captured->toIso8601String();
            $response['received_at'] = now()->toIso8601String();
            DB::table('recognition_events')->insert([
                'client_uuid' => $data['client_uuid'], 'camera_id' => $camera->id,
                'student_id' => $student->id, 'session_id' => $session->id, 'event_type' => $type,
                'captured_at' => $captured, 'received_at' => now(), 'was_offline' => $delayed,
                'response' => json_encode($response),
            ]);
            return $response;
        }, 3);
    }
}
