<?php

namespace App\Services;

use App\Exceptions\EnrollmentPhotoRejectedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * System-first check for parent face photos (before teacher review).
 */
class EnrollmentPhotoValidator
{
    public function __construct(private RecognitionProcessService $recognition)
    {
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{ok: bool, reason: string, faces: int, descriptor: ?array, message: string}>
     */
    public function validateAll(array $files): array
    {
        $results = [];

        foreach (array_values($files) as $index => $file) {
            $result = $this->validateOne($file);
            if (! ($result['ok'] ?? false)) {
                $which = count($files) > 1 ? 'Photo '.($index + 1).': ' : '';
                throw new EnrollmentPhotoRejectedException(
                    (string) ($result['reason'] ?? 'INVALID_IMAGE'),
                    $which.((string) ($result['message'] ?? 'Recapture the photo and try again.'))
                );
            }
            $results[] = $result;
        }

        return $results;
    }

    /**
     * @return array{ok: bool, reason: string, faces: int, descriptor: ?array, message: string}
     */
    public function validateOne(UploadedFile $file): array
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $mode = (string) config('recognition.photo_validation', 'auto');

        if ($mode === 'off') {
            return $this->phpStructuralCheck($path);
        }

        if ($this->recognition->pythonAvailable()) {
            return $this->pythonCheck($path, $mode === 'required');
        }

        if ($mode === 'required') {
            throw new EnrollmentPhotoRejectedException(
                'PHOTO_VALIDATION_UNAVAILABLE',
                'The system could not validate this photo. Try again from a school PC, or recapture later.'
            );
        }

        return $this->phpStructuralCheck($path);
    }

    /**
     * @return array{ok: bool, reason: string, faces: int, descriptor: ?array, message: string}
     */
    private function pythonCheck(string $path, bool $required): array
    {
        $process = new Process(
            [$this->recognition->pythonBinary(), '-u', 'validate_photo.py', $path],
            $this->recognition->directory(),
            null,
            null,
            20
        );
        $process->run();
        $payload = json_decode($process->getOutput(), true);

        if (is_array($payload) && array_key_exists('ok', $payload)) {
            return [
                'ok' => (bool) $payload['ok'],
                'reason' => (string) ($payload['reason'] ?? 'INVALID_IMAGE'),
                'faces' => (int) ($payload['faces'] ?? 0),
                'descriptor' => is_array($payload['descriptor'] ?? null) ? $payload['descriptor'] : null,
                'message' => (string) ($payload['message'] ?? 'Recapture the photo and try again.'),
            ];
        }

        Log::warning('Enrollment photo validator returned invalid JSON.', [
            'exit' => $process->getExitCode(),
            'stderr' => substr($process->getErrorOutput(), 0, 500),
        ]);

        if ($required) {
            throw new EnrollmentPhotoRejectedException(
                'PHOTO_VALIDATION_UNAVAILABLE',
                'The system could not validate this photo. Recapture and try again.'
            );
        }

        return $this->phpStructuralCheck($path);
    }

    /**
     * @return array{ok: bool, reason: string, faces: int, descriptor: ?array, message: string}
     */
    private function phpStructuralCheck(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false || ($info[0] ?? 0) < 1) {
            return [
                'ok' => false,
                'reason' => 'INVALID_IMAGE',
                'faces' => 0,
                'descriptor' => null,
                'message' => 'This file could not be read as a photo. Upload a JPEG or PNG.',
            ];
        }

        if (($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 200) {
            return [
                'ok' => false,
                'reason' => 'TOO_SMALL',
                'faces' => 0,
                'descriptor' => null,
                'message' => 'The photo is too small. Recapture a clearer, closer photo of the student.',
            ];
        }

        return [
            'ok' => true,
            'reason' => 'OK',
            'faces' => 0,
            'descriptor' => null,
            'message' => 'Photo passed basic size checks.',
        ];
    }
}
