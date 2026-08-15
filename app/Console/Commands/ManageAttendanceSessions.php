<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Services\AttendanceService;
use App\Services\SchoolCalendar;
use Illuminate\Console\Command;

class ManageAttendanceSessions extends Command
{
    protected $signature = 'attendance:manage-sessions';

    protected $description = 'Auto-open/close attendance sessions from schedules and ad-hoc timeouts';

    public function handle(AttendanceService $service, SchoolCalendar $calendar): int
    {
        $now = now();
        $today = $now->isoWeekday();      // 1 (Mon) .. 7 (Sun)
        $nowTime = $now->format('H:i:s');
        $opened = 0;
        $closed = 0;

        $schedules = Schedule::with('section')
            ->where('is_active', true)
            ->where('day_of_week', $today)
            ->get();

        $autoOpen = $calendar->shouldAutoOpenSessions($now);
        if (! $autoOpen) {
            $reason = $calendar->isWeekend($now) ? 'weekend' : 'no-class day';
            $this->info("Skipping auto-open ({$reason}).");
        }

        foreach ($schedules as $schedule) {
            if (! $schedule->section) {
                continue;
            }

            $withinWindow = $nowTime >= $schedule->start_time && $nowTime < $schedule->end_time;

            if ($autoOpen && $withinWindow) {
                $before = AttendanceSession::where('section_id', $schedule->section_id)
                    ->where('schedule_id', $schedule->id)
                    ->whereDate('session_date', $now->toDateString())
                    ->where('status', 'open')
                    ->exists();

                $service->openSession($schedule->section, $now, $schedule);

                if (! $before) {
                    $opened++;
                    $this->info("Opened schedule session: section {$schedule->section_id} schedule {$schedule->id}");
                    try {
                        app(\App\Services\RecognitionProcessService::class)->ensureRunning();
                    } catch (\Throwable) {
                        // Recognition only exists on the school PC.
                    }
                }

                continue;
            }

            if ($nowTime >= $schedule->end_time) {
                $session = AttendanceSession::where('section_id', $schedule->section_id)
                    ->where('schedule_id', $schedule->id)
                    ->whereDate('session_date', $now->toDateString())
                    ->where('status', 'open')
                    ->first();

                if ($session) {
                    $service->closeSession($session);
                    $closed++;
                    $this->info("Closed schedule session #{$session->id} (past end_time)");
                }
            }
        }

        $stale = AttendanceSession::with('section')
            ->where('status', 'open')
            ->whereNotNull('opened_at')
            ->get();

        foreach ($stale as $session) {
            $maxMinutes = $session->section?->sessionMaxMinutes()
                ?: (int) config('attendance.session_max_minutes', 360);

            if ($maxMinutes <= 0) {
                continue;
            }

            if ($session->opened_at->lte($now->copy()->subMinutes($maxMinutes))) {
                $service->closeSession($session);
                $closed++;
                $this->info("Closed session #{$session->id} (open ≥ {$maxMinutes} min)");
            }
        }

        $this->info("Attendance sessions processed: {$opened} newly opened, {$closed} closed.");

        return self::SUCCESS;
    }
}
