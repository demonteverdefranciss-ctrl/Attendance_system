<?php

namespace Tests\Unit;

use App\Exceptions\EnrollmentPhotoRejectedException;
use App\Services\EnrollmentPhotoValidator;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EnrollmentPhotoValidatorTest extends TestCase
{
    public function test_rejects_a_photo_that_has_no_face(): void
    {
        $file = UploadedFile::fake()->image('object.jpg', 640, 480);

        try {
            app(EnrollmentPhotoValidator::class)->validateAll([$file]);
            $this->fail('Expected non-face photos to be rejected.');
        } catch (EnrollmentPhotoRejectedException $e) {
            $this->assertSame('NO_FACE', $e->reasonCode);
            $this->assertStringContainsString('No face was detected', $e->getMessage());
        }
    }

    public function test_rejects_an_undersized_photo(): void
    {
        $file = UploadedFile::fake()->image('tiny.jpg', 80, 80);

        try {
            app(EnrollmentPhotoValidator::class)->validateAll([$file]);
            $this->fail('Expected tiny photos to be rejected.');
        } catch (EnrollmentPhotoRejectedException $e) {
            $this->assertSame('TOO_SMALL', $e->reasonCode);
        }
    }
}
