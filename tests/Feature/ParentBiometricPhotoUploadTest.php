<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\PicoFaceDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParentBiometricPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_cannot_submit_a_non_face_photo(): void
    {
        [$user, $student] = $this->parentWithChild();

        $this->actingAs($user)
            ->from(route('parent.biometrics.index'))
            ->post(route('parent.biometric-photos.store'), [
                'student_id' => $student->id,
                'consent_acknowledged' => '1',
                'photos' => [UploadedFile::fake()->image('full-body.jpg', 800, 600)],
            ])
            ->assertRedirect(route('parent.biometrics.index'))
            ->assertSessionHas('error');

        $this->assertStringContainsString(
            'No face was detected',
            session('error')
        );
        $this->assertDatabaseCount('biometric_photo_submissions', 0);
    }

    public function test_parent_can_submit_a_detected_face_photo(): void
    {
        config(['recognition.photo_validation' => 'off']);
        $this->mock(PicoFaceDetector::class, function ($mock) {
            $mock->shouldReceive('orientedDimensions')->andReturn([640, 480]);
            $mock->shouldReceive('detect')->andReturn([
                ['x' => 80, 'y' => 40, 'size' => 220, 'score' => 40.0],
            ]);
        });

        [$user, $student] = $this->parentWithChild();

        $this->actingAs($user)
            ->from(route('parent.biometrics.index'))
            ->post(route('parent.biometric-photos.store'), [
                'student_id' => $student->id,
                'consent_acknowledged' => '1',
                'photos' => [UploadedFile::fake()->image('face.jpg', 640, 480)],
            ])
            ->assertRedirect(route('parent.biometrics.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('biometric_photo_submissions', 1);
    }

    public function test_parent_sees_a_photo_field_message_for_a_non_image(): void
    {
        [$user, $student] = $this->parentWithChild();

        $this->actingAs($user)
            ->from(route('parent.biometrics.index'))
            ->post(route('parent.biometric-photos.store'), [
                'student_id' => $student->id,
                'consent_acknowledged' => '1',
                'photos' => [UploadedFile::fake()->create('notes.txt', 20, 'text/plain')],
            ])
            ->assertSessionHasErrors('photos.0');

        $this->assertStringContainsString(
            'JPEG or PNG',
            collect(session('errors')->get('photos.0'))->implode(' ')
        );
        $this->assertDatabaseCount('biometric_photo_submissions', 0);
    }

    /**
     * @return array{0: User, 1: Student}
     */
    private function parentWithChild(): array
    {
        $role = Role::create([
            'name' => 'parent',
            'description' => 'Parent',
        ]);

        $user = User::create([
            'role_id' => $role->id,
            'username' => 'test_parent',
            'name' => 'Test Parent',
            'email' => 'parent@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $guardian = Guardian::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Parent',
        ]);

        $student = Student::create([
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'lrn' => '136000009999',
            'is_active' => true,
        ]);

        $guardian->students()->attach($student->id, [
            'relationship' => 'mother',
            'is_primary' => true,
        ]);

        $user->setRelation('role', $role);
        $user->setRelation('guardian', $guardian);

        return [$user, $student];
    }
}
