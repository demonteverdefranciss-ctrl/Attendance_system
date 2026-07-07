<?php

namespace App\Services;

use App\Models\FaceData;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class BiometricPrivacyService
{
    public function __construct(private AuditService $audit)
    {
    }

    /**
     * @return array{records: int, files: int}
     */
    public function purgeForStudent(Student $student, bool $dryRun = false): array
    {
        $records = FaceData::where('student_id', $student->id)->get();

        return $this->purgeRecords($records, 'biometric_purged_consent', $dryRun);
    }

    /**
     * @return array{records: int, files: int, consent_revoked: int, stale: int}
     */
    public function purgeStale(bool $dryRun = false): array
    {
        $retentionDays = (int) config('security.biometric.retention_days', 365);
        $cutoff = now()->subDays($retentionDays);

        $consentRevoked = FaceData::query()
            ->whereHas('student', fn ($q) => $q->where('consent_biometric', false)->orWhere('is_active', false))
            ->get();

        $stale = FaceData::query()
            ->where('is_active', false)
            ->where('updated_at', '<', $cutoff)
            ->whereHas('student', fn ($q) => $q->where('consent_biometric', true)->where('is_active', true))
            ->get();

        $toPurge = $consentRevoked->merge($stale)->unique('id');

        $result = $this->purgeRecords($toPurge, 'biometric_purged_retention', $dryRun);

        return [
            'records' => $result['records'],
            'files' => $result['files'],
            'consent_revoked' => $consentRevoked->count(),
            'stale' => $stale->count(),
        ];
    }

    /**
     * @param  Collection<int, FaceData>  $records
     * @return array{records: int, files: int}
     */
    private function purgeRecords(Collection $records, string $auditAction, bool $dryRun): array
    {
        $deletedRecords = 0;
        $deletedFiles = 0;

        foreach ($records as $faceData) {
            if ($faceData->image_path && Storage::disk('local')->exists($faceData->image_path)) {
                if (! $dryRun) {
                    Storage::disk('local')->delete($faceData->image_path);
                }
                $deletedFiles++;
            }

            if (! $dryRun) {
                $this->audit->log(
                    action: $auditAction,
                    userId: null,
                    entity: $faceData,
                    oldValues: [
                        'student_id' => $faceData->student_id,
                        'image_path' => $faceData->image_path,
                        'model_version' => $faceData->model_version,
                    ],
                    newValues: ['purged' => true],
                );

                $faceData->delete();
            }

            $deletedRecords++;
        }

        return ['records' => $deletedRecords, 'files' => $deletedFiles];
    }
}
