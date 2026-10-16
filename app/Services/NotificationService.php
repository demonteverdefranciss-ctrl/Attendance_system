<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\NoClassDay;
use App\Models\Notification;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Queue guardian notifications for attendance events.
     */
    public function queueAttendanceEvent(Student $student, AttendanceRecord $record, bool $wasOffline = false): void
    {
        $type = $this->eventTypeForStatus($record->status);
        if (! $type) {
            return;
        }

        $student->loadMissing('guardians');

        foreach ($student->guardians as $guardian) {
            [$title, $body] = $this->attendanceMessage($type, $student->full_name);

            if ($wasOffline && $record->time_in) {
                $body = "{$student->full_name} was recorded {$record->status} at "
                    .$record->time_in->format('M j, Y g:i A')
                    .'. This update was delayed while the attendance device could not synchronize.';
            }

            $this->createAttendanceNotification(
                $guardian,
                $student,
                $record,
                $type,
                $title,
                $body,
                $wasOffline,
            );
        }
    }

    /**
     * Notify guardians when a teacher records a student's manual time-out.
     */
    public function queueManualTimeOutEvent(Student $student, AttendanceRecord $record): void
    {
        if (! $record->time_out) {
            return;
        }

        $student->loadMissing('guardians');
        $title = 'Early Time-out Recorded';
        $body = "{$student->full_name} was signed out early at "
            .$record->time_out->format('M j, Y g:i A').'.';

        foreach ($student->guardians as $guardian) {
            $this->createAttendanceNotification(
                $guardian,
                $student,
                $record,
                'early_time_out',
                $title,
                $body,
            );
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
                SendPushNotificationJob::dispatch($notification->id)->afterCommit();
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
            SendPushNotificationJob::dispatch($notification->id)->afterCommit();
        } else {
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    /**
     * Notify all parents and teachers that a no-class day was marked.
     */
    public function queueNoClassDayNotice(NoClassDay $day): void
    {
        $dateLabel = $day->date?->format('F j, Y (l)') ?? 'the selected date';
        $label = $day->name ? trim((string) $day->name) : 'No class';
        $title = 'No class day announced';
        $body = "School will have no class on {$dateLabel}".($day->name ? " — {$label}" : '').'. Attendance will not auto-open that day.';

        Guardian::query()
            ->orderBy('id')
            ->select(['id', 'notify_pref'])
            ->chunkById(100, function ($guardians) use ($day, $title, $body) {
                foreach ($guardians as $guardian) {
                    $notification = Notification::create([
                        'guardian_id' => $guardian->id,
                        'student_id' => null,
                        'channel' => 'push',
                        'type' => 'no_class_day',
                        'title' => $title,
                        'body' => $body,
                        'payload' => [
                            'no_class_day_id' => $day->id,
                            'date' => $day->date?->toDateString(),
                            'name' => $day->name,
                            'source' => $day->source,
                        ],
                        'status' => 'pending',
                    ]);

                    if ($guardian->notify_pref === 'push') {
                        SendPushNotificationJob::dispatch($notification->id)->afterCommit();
                    } else {
                        $notification->update(['status' => 'sent', 'sent_at' => now()]);
                    }
                }
            });

        Teacher::query()
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($teachers) use ($day, $title, $body) {
                foreach ($teachers as $teacher) {
                    TeacherNotification::create([
                        'teacher_id' => $teacher->id,
                        'type' => 'no_class_day',
                        'title' => $title,
                        'body' => $body,
                        'payload' => [
                            'no_class_day_id' => $day->id,
                            'date' => $day->date?->toDateString(),
                            'name' => $day->name,
                            'source' => $day->source,
                        ],
                    ]);
                }
            });
    }

    /**
     * Send one summary notification when multiple no-class weekdays are added.
     *
     * @param  array<int, NoClassDay>  $days
     */
    public function queueNoClassDayRangeNotice(array $days, ?string $name = null): void
    {
        if ($days === []) {
            return;
        }

        $dates = collect($days)
            ->map(fn (NoClassDay $day) => $day->date?->toDateString())
            ->filter()
            ->values()
            ->all();
        if ($dates === []) {
            return;
        }

        $from = Carbon::parse($dates[0])->format('F j, Y');
        $to = Carbon::parse($dates[count($dates) - 1])->format('F j, Y');
        $title = 'No-class days announced';
        $body = 'No-class days have been added for '.$from
            .($from === $to ? '' : ' through '.$to)
            .' ('.count($dates).' weekdays)'
            .($name ? " — {$name}" : '')
            .'. Attendance will not auto-open on those dates.';
        $payload = [
            'dates' => $dates,
            'name' => $name,
            'source' => NoClassDay::SOURCE_MANUAL,
        ];

        Guardian::query()
            ->orderBy('id')
            ->select(['id', 'notify_pref'])
            ->chunkById(100, function ($guardians) use ($title, $body, $payload) {
                foreach ($guardians as $guardian) {
                    $wantsPush = $guardian->notify_pref === 'push';
                    $notification = Notification::create([
                        'guardian_id' => $guardian->id,
                        'student_id' => null,
                        'channel' => 'push',
                        'type' => 'no_class_day_range',
                        'title' => $title,
                        'body' => $body,
                        'payload' => $payload,
                        'status' => $wantsPush ? 'pending' : 'sent',
                        'sent_at' => $wantsPush ? null : now(),
                    ]);

                    if ($wantsPush) {
                        SendPushNotificationJob::dispatch($notification->id)->afterCommit();
                    }
                }
            });

        Teacher::query()
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($teachers) use ($title, $body, $payload) {
                foreach ($teachers as $teacher) {
                    TeacherNotification::create([
                        'teacher_id' => $teacher->id,
                        'type' => 'no_class_day_range',
                        'title' => $title,
                        'body' => $body,
                        'payload' => $payload,
                    ]);
                }
            });
    }

    private function eventTypeForStatus(string $status): ?string
    {
        return match ($status) {
            'present' => 'arrival',
            'late' => 'late',
            'absent' => 'absent',
            'excused' => 'excused',
            default => null,
        };
    }

    private function createAttendanceNotification(
        Guardian $guardian,
        Student $student,
        AttendanceRecord $record,
        string $type,
        string $title,
        string $body,
        bool $wasOffline = false,
    ): void {
        $wantsPush = $guardian->notify_pref === 'push';
        $notification = Notification::create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'channel' => 'push',
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'payload' => [
                'attendance_record_id' => $record->id,
                'was_offline' => $wasOffline,
                'status' => $record->status,
                'time_in' => $record->time_in?->toDateTimeString(),
                'time_out' => $record->time_out?->toDateTimeString(),
                'session_id' => $record->session_id,
            ],
            'status' => $wantsPush ? 'pending' : 'sent',
            'sent_at' => $wantsPush ? null : now(),
        ]);

        if ($wantsPush) {
            SendPushNotificationJob::dispatch($notification->id)->afterCommit();
        }
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
            'excused' => ['Excused Absence', "{$studentName} has been marked excused today."],
            default => ['Attendance Update', "{$studentName} has a new attendance update."],
        };
    }
}
