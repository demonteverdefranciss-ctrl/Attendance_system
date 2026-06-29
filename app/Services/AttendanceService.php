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
    /**
     * Get (or create) the attendance session for a section on a date.
     * A schedule distinguishes AM/PM windows; ad-hoc sessions pass null.
     */
    public function openSession(Section $section, Carbon $date, ?Schedule $schedule = null): AttendanceSession
    {
        return AttendanceSession::firstOrCreate(
            [
                'section_id' => $section->id,
                'session_date' => $date->toDateString(),
                'schedule_id' => $schedule?->id,
            ],
            [
                'status' => 'open',
                'opened_at' => now(),
            ],
        );
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

        $marked = $session->records()->pluck('student_id')->all();

        $unmarked = Student::where('section_id', $session->section_id)
            ->where('is_active', true)
            ->whereNotIn('id', $marked)
            ->pluck('id');

        foreach ($unmarked as $studentId) {
            AttendanceRecord::create([
                'session_id' => $session->id,
                'student_id' => $studentId,
                'status' => 'absent',
                'method' => 'manual',
            ]);
        }

        $session->update(['status' => 'closed', 'closed_at' => now()]);
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

        $values = [
            'status' => $status,
            'time_in' => $opts['time_in'] ?? ($setsTime ? now() : null),
            'method' => $opts['method'] ?? 'manual',
            'confidence' => $opts['confidence'] ?? null,
            'camera_id' => $opts['camera_id'] ?? null,
            'marked_by' => $opts['marked_by'] ?? null,
        ];

        if (! empty($opts['client_uuid'])) {
            $values['client_uuid'] = $opts['client_uuid'];
        }

        return AttendanceRecord::updateOrCreate(
            ['session_id' => $session->id, 'student_id' => $studentId],
            $values,
        );
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
}
