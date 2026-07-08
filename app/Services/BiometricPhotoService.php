<?php

namespace App\Services;

use App\Models\BiometricPhoto;
use App\Models\BiometricPhotoSubmission;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BiometricPhotoService
{
    public const MAX_PHOTOS = 3;

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function createSubmission(Student $student, int $guardianId, array $files, bool $consentAcknowledged): BiometricPhotoSubmission
    {
        return DB::transaction(function () use ($student, $guardianId, $files, $consentAcknowledged) {
            $submission = BiometricPhotoSubmission::create([
                'student_id' => $student->id,
                'guardian_id' => $guardianId,
                'status' => 'pending',
                'consent_acknowledged' => $consentAcknowledged,
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

            return $submission->load('photos');
        });
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
