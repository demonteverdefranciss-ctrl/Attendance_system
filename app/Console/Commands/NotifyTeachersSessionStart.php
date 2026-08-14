<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\TeacherNotification;
use Illuminate\Console\Command;

class NotifyTeachersSessionStart extends Command
{
    protected $signature = 'attendance:notify-session-start';

    protected $description = 'Notify teachers 30 minutes before the 6:00 AM attendance session';

    public function handle(): int
    {
        $today = now()->toDateString();
        $teachers = Teacher::query()->orderBy('id')->get();
        $created = 0;

        foreach ($teachers as $teacher) {
            $already = TeacherNotification::where('teacher_id', $teacher->id)
                ->where('type', 'session_start_reminder')
                ->whereDate('created_at', $today)
                ->exists();

            if ($already) {
                continue;
            }

            TeacherNotification::create([
                'teacher_id' => $teacher->id,
                'type' => 'session_start_reminder',
                'title' => 'Attendance starts in 30 minutes',
                'body' => 'The attendance session will start at 6:00 AM. Open attendance so the camera can begin marking students.',
                'payload' => [
                    'session_start' => '06:00',
                    'for_date' => $today,
                ],
            ]);
            $created++;
        }

        $this->info("Session-start reminders created: {$created}.");

        return self::SUCCESS;
    }
}
