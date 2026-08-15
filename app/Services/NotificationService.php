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

    /**
     * Alert guardians that a student has 3 consecutive absents/lates and needs a letter.
     */
    public function queueConsecutiveAbsentLateAlert(Student $student, \App\Models\AttendanceExcuseRequest $request): void
    {
        $student->loadMissing('guardians');
        $name = $student->full_name;
        $dates = collect($request->streak_summary ?? [])
            ->map(fn ($row) => ($row['date'] ?? '?').' ('.$row['status'].')')
            ->implode(', ');

        foreach ($student->guardians as $guardian) {
            if ($guardian->notify_pref !== 'push') {
                // Still create an in-app notification row for the parent dashboard.
            }

            $notification = Notification::create([
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
                'channel' => 'push',
                'type' => 'consecutive_absent_late',
                'title' => 'WARNING: 3 consecutive absences',
                'body' => "{$name} has been absent 3 days in a row ({$dates}). This is a formal attendance warning. Please submit an explanation letter now.",
                'payload' => [
                    'excuse_request_id' => $request->id,
                    'streak_count' => $request->streak_count,
                    'streak_summary' => $request->streak_summary,
                ],
                'status' => 'pending',
            ]);

            if ($guardian->notify_pref === 'push') {
                SendPushNotificationJob::dispatch($notification->id);
            } else {
                $notification->update(['status' => 'sent', 'sent_at' => now()]);
            }
        }
    }

    /**
     * Notify the submitting guardian of teacher approve/reject.
     */
    public function queueExcuseDecision(\App\Models\AttendanceExcuseRequest $request, string $decision): void
    {
        $guardian = $request->guardian;
        $student = $request->student;
        if (! $guardian || ! $student) {
            return;
        }

        $approved = $decision === 'approved';
        $title = $approved ? 'Explanation letter approved' : 'Explanation letter rejected';
        $body = $approved
            ? "{$student->full_name}'s explanation was accepted. The related absences/lates were marked excused."
            : "{$student->full_name}'s explanation was not accepted.".($request->notes ? " Teacher note: {$request->notes}" : '');

        $notification = Notification::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'channel' => 'push',
            'type' => $approved ? 'excuse_approved' : 'excuse_rejected',
            'title' => $title,
            'body' => $body,
            'payload' => [
                'excuse_request_id' => $request->id,
                'decision' => $decision,
            ],
            'status' => 'pending',
        ]);

        if ($guardian->notify_pref === 'push') {
            SendPushNotificationJob::dispatch($notification->id);
        } else {
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
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
