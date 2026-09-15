<?php

namespace App\Services;

use App\Models\BiometricPhoto;
use App\Models\BiometricPhotoSubmission;
use App\Models\FaceData;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BiometricPhotoService
{
    public const MAX_PHOTOS = 3;

    /** Kilobytes. Phone JPEGs are often larger than PHP's default 2 MB cap. */
    public const MAX_PHOTO_KB = 10240;

    /**
     * @return array<string, array<int, string>>
     */
    public static function photoUploadRules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png', 'max:'.self::MAX_PHOTO_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function photoUploadMessages(): array
    {
        return [
            'photos.required' => 'Choose 1–3 photos of the student\'s face.',
            'photos.min' => 'Choose at least one photo of the student\'s face.',
            'photos.max' => 'You can upload at most '.self::MAX_PHOTOS.' photos.',
            'photos.*.image' => 'Each file must be a JPEG or PNG photo.',
            'photos.*.mimes' => 'Each file must be a JPEG or PNG photo.',
            'photos.*.max' => 'Each photo must be 10 MB or smaller.',
        ];
    }

    public function __construct(private EnrollmentPhotoValidator $validator)
    {
    }

    /**
     * System-validates photos first. Invalid files never reach teacher review.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function createSubmission(Student $student, int $guardianId, array $files, bool $consentAcknowledged): BiometricPhotoSubmission
    {
        $results = $this->validator->validateAll($files);
        $summary = array_map(fn (array $row) => [
            'ok' => $row['ok'],
            'reason' => $row['reason'],
            'faces' => $row['faces'],
            'descriptor' => ($row['descriptor'] ?? null) !== null,
        ], $results);

        return DB::transaction(function () use ($student, $guardianId, $files, $consentAcknowledged, $results, $summary) {
            $submission = BiometricPhotoSubmission::create([
                'student_id' => $student->id,
                'guardian_id' => $guardianId,
                'status' => 'pending',
                'consent_acknowledged' => $consentAcknowledged,
                'system_validated_at' => now(),
                'validation_summary' => $summary,
            ]);

            foreach (array_values($files) as $index => $file) {
                $path = $file->store("biometric-uploads/{$submission->id}", 'local');
                BiometricPhoto::create([
                    'submission_id' => $submission->id,
                    'storage_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'sort_order' => $index,
                ]);
            }

            $this->storePendingDescriptor($student, $results);

            return $submission->load('photos');
        });
    }

    /**
     * @param  array<int, array{descriptor: mixed}>  $results
     */
    private function storePendingDescriptor(Student $student, array $results): void
    {
        $vectors = [];
        foreach ($results as $row) {
            $vector = $row['descriptor'] ?? null;
            if (is_array($vector) && $vector !== []) {
                $vectors[] = $vector;
            }
        }

        if ($vectors === []) {
            return;
        }

        $length = count($vectors[0]);
        $mean = array_fill(0, $length, 0.0);
        foreach ($vectors as $vector) {
            foreach ($vector as $i => $value) {
                if ($i >= $length) {
                    break;
                }
                $mean[$i] += (float) $value;
            }
        }
        $count = count($vectors);
        $mean = array_map(fn (float $value) => $value / $count, $mean);

        FaceData::updateOrCreate(
            ['student_id' => $student->id, 'is_active' => false],
            [
                'embedding' => json_encode($mean),
                'model_version' => 1,
            ]
        );
    }

    public function approve(BiometricPhotoSubmission $submission, Teacher $teacher, ?string $notes = null): void
    {
        DB::transaction(function () use ($submission, $teacher, $notes) {
            $submission->update([
                'status' => 'approved',
                'teacher_id' => $teacher->id,
                'reviewed_at' => now(),
                'notes' => $notes,
            ]);

            $submission->student?->update(['consent_biometric' => true]);

            FaceData::where('student_id', $submission->student_id)
                ->where('is_active', false)
                ->update(['is_active' => true]);
        });
    }

    public function reject(BiometricPhotoSubmission $submission, Teacher $teacher, ?string $notes = null): void
    {
        DB::transaction(function () use ($submission, $teacher, $notes) {
            $this->deleteSubmissionFiles($submission);

            $submission->update([
                'status' => 'rejected',
                'teacher_id' => $teacher->id,
                'reviewed_at' => now(),
                'notes' => $notes,
            ]);

            FaceData::where('student_id', $submission->student_id)
                ->where('is_active', false)
                ->delete();
        });
    }

    public function deleteSubmissionFiles(BiometricPhotoSubmission $submission): void
    {
        foreach ($submission->photos as $photo) {
            if (Storage::disk('local')->exists($photo->storage_path)) {
                Storage::disk('local')->delete($photo->storage_path);
            }
        }

        Storage::disk('local')->deleteDirectory("biometric-uploads/{$submission->id}");
    }

    public function markSynced(BiometricPhotoSubmission $submission): void
    {
        $submission->update(['synced_at' => now()]);
    }
}
