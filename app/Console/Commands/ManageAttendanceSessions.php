<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Services\AttendanceService;
use Illuminate\Console\Command;

class ManageAttendanceSessions extends Command
{
    protected $signature = 'attendance:manage-sessions';

    protected $description = 'Auto-open and auto-close attendance sessions based on section schedules';

    public function handle(AttendanceService $service): int
    {
        $now = now();
        $today = $now->isoWeekday();      // 1 (Mon) .. 7 (Sun)
        $nowTime = $now->format('H:i:s');

        $schedules = Schedule::with('section')
            ->where('is_active', true)
            ->where('day_of_week', $today)
            ->get();

        $opened = 0;
        $closed = 0;

        foreach ($schedules as $schedule) {
            if (! $schedule->section) {
                continue;
            }

            $withinWindow = $nowTime >= $schedule->start_time && $nowTime < $schedule->end_time;

            if ($withinWindow) {
                $service->openSession($schedule->section, $now, $schedule);
                $opened++;

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
                }
            }
        }

        $this->info("Attendance sessions processed: {$opened} open, {$closed} closed.");

        return self::SUCCESS;
    }
}
