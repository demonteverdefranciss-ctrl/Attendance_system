<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\AttendanceRecord;
use App\Models\Notification;
use App\Models\Student;

class NotificationService
{
    /**
     * Queue guardian notifications for attendance events.
     */
    public function queueAttendanceEvent(Student $student, AttendanceRecord $record): void
    {
        $type = $this->eventTypeForStatus($record->status);
        if (! $type) {
            return;
        }

        $student->loadMissing('guardians');

        foreach ($student->guardians as $guardian) {
            if ($guardian->notify_pref !== 'push') {
                continue;
            }

            [$title, $body] = $this->attendanceMessage($type, $student->full_name);

            $notification = Notification::create([
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
                'channel' => 'push',
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'payload' => [
                    'attendance_record_id' => $record->id,
                    'status' => $record->status,
                    'time_in' => $record->time_in?->toDateTimeString(),
                    'time_out' => $record->time_out?->toDateTimeString(),
                    'session_id' => $record->session_id,
                ],
                'status' => 'pending',
            ]);

            SendPushNotificationJob::dispatch($notification->id);
        }
    }

    private function eventTypeForStatus(string $status): ?string
    {
        return match ($status) {
            'present' => 'arrival',
            'late' => 'late',
            'absent' => 'absent',
            default => null,
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function attendanceMessage(string $type, string $studentName): array
    {
        return match ($type) {
            'arrival' => ['Student Arrival', "{$studentName} has arrived at school."],
            'late' => ['Late Arrival', "{$studentName} has been marked late today."],
            'absent' => ['Absent Notice', "{$studentName} has been marked absent today."],
            default => ['Attendance Update', "{$studentName} has a new attendance update."],
        };
    }
}
