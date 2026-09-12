<?php

namespace App\Services;

use App\Exceptions\EnrollmentPhotoRejectedException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * System-first check for parent face photos (before teacher review).
 * Rejects non-face images and full-body / distant shots.
 */
class EnrollmentPhotoValidator
{
    public const MIN_IMAGE_PX = 200;

    public const MIN_FACE_HEIGHT_RATIO = 0.14;

    public const MIN_FACE_WIDTH_RATIO = 0.10;

    public function __construct(
        private RecognitionProcessService $recognition,
        private PicoFaceDetector $faces,
    ) {
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
                    $which.((string) ($result['message'] ?? 'Recapture a close-up of the student\'s face and try again.'))
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
            return $this->phpFaceCheck($path);
        }

        $python = $this->pythonCommand();
        if ($python !== null) {
            return $this->pythonCheck($path, $python, $mode === 'required');
        }

        if ($mode === 'required') {
            throw new EnrollmentPhotoRejectedException(
                'PHOTO_VALIDATION_UNAVAILABLE',
                'The system could not validate this photo. Try again from a school PC, or recapture later.'
            );
        }

        return $this->phpFaceCheck($path);
    }

    /**
     * @param  array<int, string>  $command
     * @return array{ok: bool, reason: string, faces: int, descriptor: ?array, message: string}
     */
    private function pythonCheck(string $path, array $command, bool $required): array
    {
        $process = new Process(
            [...$command, $path],
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
                'message' => (string) ($payload['message'] ?? 'Recapture a close-up of the student\'s face and try again.'),
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

        return $this->phpFaceCheck($path);
    }

    /**
     * @return array{ok: bool, reason: string, faces: int, descriptor: ?array, message: string}
     */
    private function phpFaceCheck(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false || ($info[0] ?? 0) < 1) {
            return $this->fail('INVALID_IMAGE', 0, 'This file could not be read as a photo. Upload a JPEG or PNG.');
        }

        [$imageW, $imageH] = $this->faces->orientedDimensions($path);
        if ($imageW < self::MIN_IMAGE_PX || $imageH < self::MIN_IMAGE_PX) {
            return $this->fail('TOO_SMALL', 0, 'The photo is too small. Recapture a clearer, closer photo of the student\'s face.');
        }

        if (! extension_loaded('gd')) {
            return $this->fail(
                'PHOTO_VALIDATION_UNAVAILABLE',
                0,
                'The system could not check for a face on this photo. Recapture and try again.'
            );
        }

        try {
            $faces = $this->faces->detect($path);
        } catch (\Throwable $e) {
            Log::warning('PHP face detector failed.', ['error' => $e->getMessage()]);

            return $this->fail(
                'PHOTO_VALIDATION_UNAVAILABLE',
                0,
                'The system could not check for a face on this photo. Recapture and try again.'
            );
        }

        $picked = $this->selectPrimaryFace($faces);
        if ($picked['status'] === 'none') {
            return $this->fail(
                'NO_FACE',
                0,
                'No face was detected. Upload a clear, front-facing photo of the student — not an object, screenshot, or full-body photo.'
            );
        }
        if ($picked['status'] === 'multiple') {
            return $this->fail(
                'MULTIPLE_FACES',
                count($faces),
                'More than one face was found. Recapture a photo with only the student.'
            );
        }

        $face = $picked['face'];
        $size = (int) ($face['size'] ?? 0);
        if ($size < $imageH * self::MIN_FACE_HEIGHT_RATIO || $size < $imageW * self::MIN_FACE_WIDTH_RATIO) {
            return $this->fail(
                'NOT_CLOSE_UP',
                1,
                'This looks like a full-body or distant photo. Recapture a closer photo of the student\'s face.'
            );
        }

        return [
            'ok' => true,
            'reason' => 'OK',
            'faces' => 1,
            'descriptor' => null,
            'message' => 'Face is valid and usable for recognition.',
        ];
    }

    /**
     * Ignore weak extra boxes (common on phone photos) and keep the main face.
     *
     * @param  array<int, array{x?: int, y?: int, size?: int, score?: float}>  $faces
     * @return array{status: string, face: ?array}
     */
    public function selectPrimaryFace(array $faces): array
    {
        if ($faces === []) {
            return ['status' => 'none', 'face' => null];
        }

        usort($faces, function (array $a, array $b) {
            $score = ((float) ($b['score'] ?? 0)) <=> ((float) ($a['score'] ?? 0));

            return $score !== 0 ? $score : ((int) ($b['size'] ?? 0)) <=> ((int) ($a['size'] ?? 0));
        });

        $best = $faces[0];
        $bestSize = (int) ($best['size'] ?? 0);
        $bestScore = (float) ($best['score'] ?? 0);

        foreach (array_slice($faces, 1) as $other) {
            $similarSize = (int) ($other['size'] ?? 0) >= $bestSize * 0.6;
            $similarScore = (float) ($other['score'] ?? 0) >= max(10.0, $bestScore * 0.4);
            if ($similarSize && $similarScore) {
                return ['status' => 'multiple', 'face' => $best];
            }
        }

        return ['status' => 'one', 'face' => $best];
    }

    /**
     * @return array{ok: bool, reason: string, faces: int, descriptor: ?array, message: string}
     */
    private function fail(string $reason, int $faces, string $message): array
    {
        return [
            'ok' => false,
            'reason' => $reason,
            'faces' => $faces,
            'descriptor' => null,
            'message' => $message,
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function pythonCommand(): ?array
    {
        if ($this->recognition->pythonAvailable()) {
            return [$this->recognition->pythonBinary(), '-u', 'validate_photo.py'];
        }

        $script = $this->recognition->directory().DIRECTORY_SEPARATOR.'validate_photo.py';
        if (! is_file($script)) {
            return null;
        }

        foreach (['python3', 'python'] as $bin) {
            $probe = new Process([$bin, '--version']);
            $probe->run();
            if ($probe->isSuccessful()) {
                return [$bin, '-u', 'validate_photo.py'];
            }
        }

        return null;
    }
}
