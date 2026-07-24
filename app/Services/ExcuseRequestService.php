<?php

namespace App\Services;

use App\Models\AttendanceExcuseRequest;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

class ExcuseRequestService
{
    public const STREAK_THRESHOLD = 3;

    public function __construct(
        private NotificationService $notifications,
        private AuditService $audit,
    ) {
    }

    /**
     * After an absent/late mark, open an excuse request if the student has
     * STREAK_THRESHOLD consecutive absent|late records (newest first).
     * present/excused reset the streak.
     */
    public function evaluateAfterMark(int $studentId): void
    {
        $student = Student::with('guardians')->find($studentId);
        if (! $student) {
            return;
        }

        $openExists = AttendanceExcuseRequest::where('student_id', $studentId)
            ->whereIn('status', ['awaiting_letter', 'pending'])
            ->exists();

        if ($openExists) {
            return;
        }

        $recent = AttendanceRecord::query()
            ->where('student_id', $studentId)
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->orderByDesc('attendance_sessions.session_date')
            ->orderByDesc('attendance_records.id')
            ->limit(20)
            ->get([
                'attendance_records.id',
                'attendance_records.status',
                'attendance_sessions.session_date',
            ]);

        $streakIds = [];
        $summary = [];

        foreach ($recent as $row) {
            if (! in_array($row->status, ['absent', 'late'], true)) {
                break;
            }
            $streakIds[] = (int) $row->id;
            $summary[] = [
                'record_id' => (int) $row->id,
                'date' => $row->session_date
                    ? (is_string($row->session_date) ? $row->session_date : $row->session_date->toDateString())
                    : null,
                'status' => $row->status,
            ];
            if (count($streakIds) >= self::STREAK_THRESHOLD) {
                break;
            }
        }

        if (count($streakIds) < self::STREAK_THRESHOLD) {
            return;
        }

        $request = AttendanceExcuseRequest::create([
            'student_id' => $studentId,
            'guardian_id' => null,
            'streak_count' => count($streakIds),
            'attendance_record_ids' => $streakIds,
            'streak_summary' => $summary,
            'status' => 'awaiting_letter',
            'notified_at' => now(),
        ]);

        $this->notifications->queueConsecutiveAbsentLateAlert($student, $request);

        $this->audit->log(
            action: 'excuse_request_opened',
            userId: null,
            entity: $request,
            newValues: [
                'student_id' => $studentId,
                'streak_count' => count($streakIds),
                'attendance_record_ids' => $streakIds,
                'status' => 'awaiting_letter',
            ],
        );
    }

    public function submitLetter(Guardian $guardian, AttendanceExcuseRequest $request, string $letterBody): AttendanceExcuseRequest
    {
        if (! $guardian->students()->whereKey($request->student_id)->exists()) {
            abort(403, 'This child is not linked to your account.');
        }

        if ($request->status !== 'awaiting_letter') {
            throw new \InvalidArgumentException('This request is not waiting for an explanation letter.');
        }

        $request->update([
            'guardian_id' => $guardian->id,
            'letter_body' => $letterBody,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->audit->log(
            action: 'excuse_letter_submitted',
            userId: $guardian->user_id,
            entity: $request,
            oldValues: ['status' => 'awaiting_letter'],
            newValues: ['status' => 'pending', 'guardian_id' => $guardian->id],
        );

        return $request->fresh();
    }

    public function approve(Teacher $teacher, AttendanceExcuseRequest $request, ?string $notes = null): AttendanceExcuseRequest
    {
        $this->authorizeTeacher($teacher, $request);

        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending explanation letters can be approved.');
        }

        return DB::transaction(function () use ($teacher, $request, $notes) {
            $ids = $request->attendance_record_ids ?? [];
            $records = AttendanceRecord::with('session')
                ->whereIn('id', $ids)
                ->where('student_id', $request->student_id)
                ->get();

            foreach ($records as $record) {
                if (! $record->session) {
                    continue;
                }
                app(AttendanceService::class)->mark($record->session, $request->student_id, 'excused', [
                    'method' => 'manual',
                    'marked_by' => $teacher->user_id,
                    'skip_notification' => true,
                    'skip_excuse_check' => true,
                ]);
            }

            $request->update([
                'status' => 'approved',
                'teacher_id' => $teacher->id,
                'notes' => $notes,
                'reviewed_at' => now(),
            ]);

            $this->audit->log(
                action: 'excuse_request_approved',
                userId: $teacher->user_id,
                entity: $request,
                oldValues: ['status' => 'pending'],
                newValues: ['status' => 'approved', 'notes' => $notes],
            );

            $this->notifications->queueExcuseDecision($request->fresh(['student', 'guardian']), 'approved');

            return $request->fresh();
        });
    }

    public function reject(Teacher $teacher, AttendanceExcuseRequest $request, ?string $notes = null): AttendanceExcuseRequest
    {
        $this->authorizeTeacher($teacher, $request);

        if ($request->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending explanation letters can be rejected.');
        }

        $request->update([
            'status' => 'rejected',
            'teacher_id' => $teacher->id,
            'notes' => $notes,
            'reviewed_at' => now(),
        ]);

        $this->audit->log(
            action: 'excuse_request_rejected',
            userId: $teacher->user_id,
            entity: $request,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'rejected', 'notes' => $notes],
        );

        $this->notifications->queueExcuseDecision($request->fresh(['student', 'guardian']), 'rejected');

        return $request->fresh();
    }

    private function authorizeTeacher(Teacher $teacher, AttendanceExcuseRequest $request): void
    {
        $request->loadMissing('student');
        $sectionId = $request->student?->section_id
            ?? Student::whereKey($request->student_id)->value('section_id');

        abort_unless(
            $sectionId && $teacher->sections()->whereKey($sectionId)->exists(),
            403
        );
    }
}
