<?php

namespace Tests\Unit;

use App\Exceptions\EnrollmentPhotoRejectedException;
use App\Services\EnrollmentPhotoValidator;
use App\Services\PicoFaceDetector;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EnrollmentPhotoValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['recognition.photo_validation' => 'off']);
    }

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

    public function test_accepts_a_single_close_up_face(): void
    {
        $this->mockDetector([
            ['x' => 80, 'y' => 40, 'size' => 220, 'score' => 40.0],
        ]);

        $file = UploadedFile::fake()->image('face.jpg', 640, 480);
        $results = app(EnrollmentPhotoValidator::class)->validateAll([$file]);

        $this->assertTrue($results[0]['ok']);
        $this->assertSame('OK', $results[0]['reason']);
    }

    public function test_ignores_a_weak_extra_detection(): void
    {
        $this->mockDetector([
            ['x' => 80, 'y' => 40, 'size' => 240, 'score' => 48.0],
            ['x' => 10, 'y' => 10, 'size' => 60, 'score' => 6.0],
        ]);

        $file = UploadedFile::fake()->image('face.jpg', 640, 480);
        $results = app(EnrollmentPhotoValidator::class)->validateAll([$file]);

        $this->assertTrue($results[0]['ok']);
    }

    public function test_rejects_two_similar_faces(): void
    {
        $this->mockDetector([
            ['x' => 40, 'y' => 40, 'size' => 200, 'score' => 40.0],
            ['x' => 300, 'y' => 40, 'size' => 190, 'score' => 36.0],
        ]);

        $file = UploadedFile::fake()->image('two.jpg', 640, 480);

        try {
            app(EnrollmentPhotoValidator::class)->validateAll([$file]);
            $this->fail('Expected two-person photos to be rejected.');
        } catch (EnrollmentPhotoRejectedException $e) {
            $this->assertSame('MULTIPLE_FACES', $e->reasonCode);
        }
    }

    /**
     * @param  array<int, array{x: int, y: int, size: int, score: float}>  $faces
     */
    private function mockDetector(array $faces): void
    {
        $this->mock(PicoFaceDetector::class, function ($mock) use ($faces) {
            $mock->shouldReceive('orientedDimensions')->andReturn([640, 480]);
            $mock->shouldReceive('detect')->andReturn($faces);
        });
    }
}
