<?php

namespace App\Services;

use App\Models\AttendanceExcuseRequest;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcuseRequestService
{
    public const STREAK_THRESHOLD = 3;

    public function __construct(
        private NotificationService $notifications,
        private AuditService $audit,
    ) {
    }

    /**
     * After an absent mark, open a required letter if the student has
     * STREAK_THRESHOLD consecutive absences. Single absences can still be
     * explained by the parent on demand via openOptional().
     * present/excused/late reset the consecutive-absent warning streak.
     */
    public function evaluateAfterMark(int $studentId): void
    {
        $student = Student::with('guardians')->find($studentId);
        if (! $student) {
            return;
        }

        [$streakIds, $summary] = $this->consecutiveAbsentStreak($studentId);
        if (count($streakIds) < self::STREAK_THRESHOLD) {
            return;
        }

        $open = AttendanceExcuseRequest::where('student_id', $studentId)
            ->where('status', 'awaiting_letter')
            ->latest('id')
            ->first();

        if ($open) {
            $alreadyWarned = (int) $open->streak_count >= self::STREAK_THRESHOLD;
            $open->update([
                'streak_count' => count($streakIds),
                'attendance_record_ids' => array_values(array_unique(array_merge(
                    $open->attendance_record_ids ?? [],
                    $streakIds,
                ))),
                'streak_summary' => $summary,
            ]);

            if (! $alreadyWarned) {
                $this->notifications->queueConsecutiveAbsentLateAlert($student, $open->fresh());
                $open->update(['notified_at' => now()]);
            }

            return;
        }

        $covered = $this->coveredRecordIds([$studentId]);
        $uncovered = array_values(array_filter($streakIds, fn ($id) => ! in_array($id, $covered, true)));
        if ($uncovered === []) {
            return;
        }

        $request = AttendanceExcuseRequest::create([
            'student_id' => $studentId,
            'guardian_id' => null,
            'streak_count' => count($streakIds),
            'attendance_record_ids' => $uncovered,
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
                'is_required' => true,
            ],
        );
    }

    public function openOptional(Guardian $guardian, int $attendanceRecordId): AttendanceExcuseRequest
    {
        $record = AttendanceRecord::with('session')->find($attendanceRecordId);
        if (! $record) {
            throw new \InvalidArgumentException('Attendance record not found.');
        }

        if (! $guardian->students()->whereKey($record->student_id)->exists()) {
            abort(403, 'This child is not linked to your account.');
        }

        if (! in_array($record->status, ['absent', 'late'], true)) {
            throw new \InvalidArgumentException('You can only explain an absence or late mark.');
        }

        if ($this->recordIsCovered((int) $record->id, (int) $record->student_id)) {
            throw new \InvalidArgumentException('An explanation letter is already in progress for this record.');
        }

        $date = $record->session?->session_date;
        $summary = [[
            'record_id' => (int) $record->id,
            'date' => $date
                ? (is_string($date) ? $date : $date->toDateString())
                : null,
            'status' => $record->status,
        ]];

        $request = AttendanceExcuseRequest::create([
            'student_id' => $record->student_id,
            'guardian_id' => $guardian->id,
            'streak_count' => 1,
            'attendance_record_ids' => [(int) $record->id],
            'streak_summary' => $summary,
            'status' => 'awaiting_letter',
        ]);

        $this->audit->log(
            action: 'excuse_request_opened',
            userId: $guardian->user_id,
            entity: $request,
            newValues: [
                'student_id' => $record->student_id,
                'streak_count' => 1,
                'attendance_record_ids' => [(int) $record->id],
                'status' => 'awaiting_letter',
                'is_required' => false,
            ],
        );

        return $request->fresh();
    }

    /**
     * Absent/late records the guardian can still explain.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function eligibleAbsences(Guardian $guardian)
    {
        $studentIds = $guardian->students()->pluck('students.id')->all();
        if ($studentIds === []) {
            return collect();
        }

        $covered = $this->coveredRecordIds($studentIds);

        return AttendanceRecord::with([
            'student:id,first_name,last_name',
            'session:id,session_date',
        ])
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['absent', 'late'])
            ->when($covered !== [], fn ($q) => $q->whereNotIn('id', $covered))
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student_id' => $r->student_id,
                'student' => $r->student?->full_name,
                'date' => $r->session?->session_date?->toDateString(),
                'status' => $r->status,
            ])
            ->values();
    }

    /**
     * @return array{0: list<int>, 1: list<array{record_id: int, date: ?string, status: string}>}
     */
    private function consecutiveAbsentStreak(int $studentId): array
    {
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
            if ($row->status !== 'absent') {
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

        return [$streakIds, $summary];
    }

    /**
     * @param  list<int>  $studentIds
     * @return list<int>
     */
    public function coveredRecordIds(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return AttendanceExcuseRequest::whereIn('student_id', $studentIds)
            ->whereIn('status', ['awaiting_letter', 'pending'])
            ->get()
            ->flatMap(fn ($r) => collect($r->attendance_record_ids ?? []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function recordIsCovered(int $recordId, int $studentId): bool
    {
        return in_array($recordId, $this->coveredRecordIds([$studentId]), true);
    }

    public function submitLetter(
        Guardian $guardian,
        AttendanceExcuseRequest $request,
        ?string $letterBody = null,
        ?UploadedFile $pdf = null,
        ?UploadedFile $photo = null,
    ): AttendanceExcuseRequest {
        if (! $guardian->students()->whereKey($request->student_id)->exists()) {
            abort(403, 'This child is not linked to your account.');
        }

        if ($request->status !== 'awaiting_letter') {
            throw new \InvalidArgumentException('This request is not waiting for an explanation letter.');
        }

        $letterBody = is_string($letterBody) ? trim($letterBody) : '';
        if ($letterBody === '' && ! $pdf) {
            throw new \InvalidArgumentException('Type an explanation or upload a PDF letter.');
        }

        $pdfPath = null;
        $pdfName = null;
        if ($pdf) {
            $pdfPath = $pdf->store("excuse-letters/{$request->id}", 'local');
            $pdfName = $this->safeOriginalName($pdf, 'explanation-letter.pdf');
        }

        $photoPath = null;
        $photoName = null;
        if ($photo) {
            $photoPath = $photo->store("excuse-letters/{$request->id}", 'local');
            $photoName = $this->safeOriginalName($photo, 'supporting-photo.jpg');
        }

        $request->update([
            'guardian_id' => $guardian->id,
            'letter_body' => $letterBody !== '' ? $letterBody : null,
            'letter_pdf_path' => $pdfPath,
            'letter_pdf_name' => $pdfName,
            'photo_path' => $photoPath,
            'photo_name' => $photoName,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->audit->log(
            action: 'excuse_letter_submitted',
            userId: $guardian->user_id,
            entity: $request,
            oldValues: ['status' => 'awaiting_letter'],
            newValues: [
                'status' => 'pending',
                'guardian_id' => $guardian->id,
                'has_pdf' => (bool) $pdfPath,
                'has_photo' => (bool) $photoPath,
            ],
        );

        return $request->fresh();
    }

    public function assertGuardianCanAccess(Guardian $guardian, AttendanceExcuseRequest $request): void
    {
        abort_unless($guardian->students()->whereKey($request->student_id)->exists(), 403);
    }

    public function assertTeacherCanAccess(Teacher $teacher, AttendanceExcuseRequest $request): void
    {
        $this->authorizeTeacher($teacher, $request);
    }

    public function attachmentResponse(AttendanceExcuseRequest $request, string $type): StreamedResponse
    {
        [$path, $downloadName] = match (strtolower($type)) {
            'pdf' => [$request->letter_pdf_path, $request->letter_pdf_name ?: 'explanation-letter.pdf'],
            'photo' => [$request->photo_path, $request->photo_name ?: 'supporting-photo.jpg'],
            default => abort(404),
        };

        abort_unless(is_string($path) && $path !== '' && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $downloadName);
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

    private function safeOriginalName(UploadedFile $file, string $fallback): string
    {
        $name = basename($file->getClientOriginalName());
        $name = preg_replace('/[^\w.\- ()]/u', '_', $name) ?: $fallback;

        return mb_substr($name, 0, 180);
    }
}
