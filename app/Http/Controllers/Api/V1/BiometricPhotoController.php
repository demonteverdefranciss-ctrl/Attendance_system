<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\BiometricPhoto;
use App\Models\BiometricPhotoSubmission;
use App\Services\BiometricPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BiometricPhotoController extends ApiController
{
    public function __construct(private BiometricPhotoService $photos)
    {
    }

    /**
     * List approved, not-yet-synced photo submissions for the recognition node.
     */
    public function approved(Request $request): JsonResponse
    {
        $submissions = BiometricPhotoSubmission::with([
            'student:id,first_name,last_name',
            'photos:id,submission_id,storage_path,original_name,sort_order',
        ])
            ->where('status', 'approved')
            ->whereNull('synced_at')
            ->latest('id')
            ->get()
            ->map(fn ($s) => [
                'submission_id' => $s->id,
                'student_id' => $s->student_id,
                'student_name' => $s->student?->full_name,
                'photos' => $s->photos->map(fn ($p) => [
                    'id' => $p->id,
                    'download_url' => url("/api/v1/biometric/photos/{$p->id}/file"),
                    'original_name' => $p->original_name,
                ])->values(),
            ]);

        return $this->ok($submissions);
    }

    /**
     * Download a single approved photo (device-authenticated).
     */
    public function file(BiometricPhoto $photo): StreamedResponse|JsonResponse
    {
        $submission = $photo->submission;

        if (! $submission || $submission->status !== 'approved') {
            return $this->fail('Photo is not available.', 'NOT_APPROVED', 404);
        }

        abort_unless(Storage::disk('local')->exists($photo->storage_path), 404);

        return Storage::disk('local')->response($photo->storage_path);
    }

    /**
     * Mark a submission as synced after the recognition node imports it.
     */
    public function markSynced(Request $request, BiometricPhotoSubmission $submission): JsonResponse
    {
        if ($submission->status !== 'approved') {
            return $this->fail('Only approved submissions can be marked synced.', 'INVALID_STATUS', 422);
        }

        $this->photos->markSynced($submission);

        return $this->ok(['submission_id' => $submission->id, 'synced_at' => $submission->fresh()->synced_at]);
    }
}
