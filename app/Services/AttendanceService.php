<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use Carbon\Carbon;

class AttendanceService
{
    public function __construct(
        private NotificationService $notifications,
        private AuditService $audit
    )
    {
    }

    /**
     * Get (or create) the attendance session for a section on a date.
     * A schedule distinguishes AM/PM windows; ad-hoc sessions pass null.
     */
    public function openSession(Section $section, Carbon $date, ?Schedule $schedule = null): AttendanceSession
    {
        // Prefer the currently open session for this section/date/schedule.
        $existingOpen = AttendanceSession::where('section_id', $section->id)
            ->whereDate('session_date', $date->toDateString())
            ->where('schedule_id', $schedule?->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if ($existingOpen) {
            return $existingOpen;
        }

        // Close any other open sessions for this section today (e.g. leftover
        // schedule + ad-hoc) so Close/Re-open and the camera stay in sync.
        AttendanceSession::where('section_id', $section->id)
            ->whereDate('session_date', $date->toDateString())
            ->where('status', 'open')
            ->update(['status' => 'closed', 'closed_at' => now()]);

        return AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => $date->toDateString(),
            'schedule_id' => $schedule?->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    /**
     * The currently open session for a section today (latest opened), if any.
     */
    public function currentOpenSession(int $sectionId): ?AttendanceSession
    {
        return AttendanceSession::where('section_id', $sectionId)
            ->whereDate('session_date', now()->toDateString())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Close a session and mark every still-unmarked active student absent.
     */
    public function closeSession(AttendanceSession $session): void
    {
        if ($session->status === 'closed') {
            return;
        }

        // Mark closed first so a slow absent-fill cannot leave the session stuck open.
        $session->update(['status' => 'closed', 'closed_at' => now()]);

        $marked = $session->records()->pluck('student_id')->all();

        $unmarked = Student::where('section_id', $session->section_id)
            ->where('is_active', true)
            ->whereNotIn('id', $marked)
            ->pluck('id');

        foreach ($unmarked as $studentId) {
            $this->mark($session, (int) $studentId, 'absent', [
                'method' => 'manual',
                'skip_notification' => true,
                'skip_audit' => true,
            ]);
        }

        if ($unmarked->isNotEmpty()) {
            $this->audit->log(
                action: 'attendance_session_closed',
                userId: null,
                entity: $session,
                oldValues: ['status' => 'open'],
                newValues: [
                    'status' => 'closed',
                    'auto_absent_count' => $unmarked->count(),
                ],
            );
        }
    }

    /**
     * Record (or update) a single student's attendance — duplicate-safe via
     * the unique(session_id, student_id) constraint.
     *
     * @param  array<string, mixed>  $opts  time_in, method, confidence, camera_id, marked_by
     */
    public function mark(AttendanceSession $session, int $studentId, string $status, array $opts = []): AttendanceRecord
    {
        $setsTime = in_array($status, ['present', 'late'], true);

        $record = AttendanceRecord::firstOrNew([
            'session_id' => $session->id,
            'student_id' => $studentId,
        ]);
        $isNew = ! $record->exists;
        $beforeStatus = $record->status;
        $beforeTimeIn = $record->time_in;

        $record->status = $status;

        if (array_key_exists('time_in', $opts)) {
            $record->time_in = $opts['time_in'];
        } elseif ($setsTime && ! $record->time_in) {
            $record->time_in = now();
        } elseif (! $setsTime) {
            $record->time_in = null;
            $record->time_out = null;
        }

        $record->method = $opts['method'] ?? ($record->method ?? 'manual');

        if (array_key_exists('confidence', $opts) || $isNew) {
            $record->confidence = $opts['confidence'] ?? null;
        }

        if (array_key_exists('camera_id', $opts) || $isNew) {
            $record->camera_id = $opts['camera_id'] ?? null;
        }

        if (array_key_exists('marked_by', $opts) || $isNew) {
            $record->marked_by = $opts['marked_by'] ?? null;
        }

        if (! empty($opts['client_uuid'])) {
            $record->client_uuid = $opts['client_uuid'];
        }

        $record->save();

        if (empty($opts['skip_notification'])) {
            $this->dispatchAttendanceNotificationIfNeeded($record, $isNew, $beforeStatus, $beforeTimeIn);
        }

        if (empty($opts['skip_audit'])) {
            $this->audit->log(
                action: 'attendance_marked',
                userId: $opts['marked_by'] ?? null,
                entity: $record,
                oldValues: [
                    'status' => $beforeStatus,
                    'time_in' => $beforeTimeIn?->toDateTimeString(),
                ],
                newValues: [
                    'status' => $record->status,
                    'time_in' => $record->time_in?->toDateTimeString(),
                    'time_out' => $record->time_out?->toDateTimeString(),
                    'method' => $record->method,
                ],
                ipAddress: $opts['ip_address'] ?? null,
                userAgent: $opts['user_agent'] ?? null
            );
        }

        return $record;
    }

    /**
     * Set a student's time-out in an existing session record.
     *
     * @param  array<string, mixed>  $opts  marked_by, client_uuid
     */
    public function recordTimeOut(AttendanceSession $session, int $studentId, ?Carbon $timeOut = null, array $opts = []): AttendanceRecord
    {
        $record = AttendanceRecord::where('session_id', $session->id)
            ->where('student_id', $studentId)
            ->first();

        if (! $record || ! in_array($record->status, ['present', 'late'], true)) {
            throw new \InvalidArgumentException('Cannot record time-out without a present/late attendance record.');
        }

        $timeOut = $timeOut ?? now();

        // Face-camera clocks / timezones can briefly skew earlier than time_in.
        if ($record->time_in && $timeOut->lt($record->time_in)) {
            $timeOut = $record->time_in->copy()->addSecond();
        }

        if ($record->time_out) {
            return $record;
        }

        $beforeTimeOut = $record->time_out;
        $record->time_out = $timeOut;

        if (array_key_exists('marked_by', $opts)) {
            $record->marked_by = $opts['marked_by'];
        }

        if (! empty($opts['client_uuid'])) {
            $record->client_uuid = $opts['client_uuid'];
        }

        $record->save();
        $this->audit->log(
            action: 'attendance_time_out_recorded',
            userId: $opts['marked_by'] ?? null,
            entity: $record,
            oldValues: [
                'time_out' => $beforeTimeOut?->toDateTimeString(),
            ],
            newValues: [
                'time_out' => $record->time_out?->toDateTimeString(),
            ],
            ipAddress: $opts['ip_address'] ?? null,
            userAgent: $opts['user_agent'] ?? null
        );

        return $record;
    }

    /**
     * Decide present vs late from an arrival time against a schedule window.
     */
    public function statusForArrival(?Schedule $schedule, Carbon $time): string
    {
        if ($schedule?->late_after && $time->format('H:i:s') > $schedule->late_after) {
            return 'late';
        }

        return 'present';
    }

    private function dispatchAttendanceNotificationIfNeeded(
        AttendanceRecord $record,
        bool $isNew,
        ?string $beforeStatus,
        mixed $beforeTimeIn
    ): void {
        $shouldNotify = match ($record->status) {
            'present', 'late' => $isNew
                || $beforeStatus !== $record->status
                || ($beforeTimeIn === null && $record->time_in !== null),
            'absent' => $isNew || $beforeStatus !== 'absent',
            default => false,
        };

        if (! $shouldNotify) {
            return;
        }

        $student = Student::find($record->student_id);
        if (! $student) {
            return;
        }

        $this->notifications->queueAttendanceEvent($student, $record);
    }
}
