<?php

namespace Tests\Feature;

use App\Models\{AttendanceRecord, AttendanceSession, Camera, Section, Student};
use App\Services\RecognitionIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Hash;
use App\Models\{Guardian, Notification, Role, User};
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OfflineRecognitionTest extends TestCase
{
    use RefreshDatabase;
    private Camera $camera;
    private Student $student;
    private AttendanceSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        Queue::fake();
        $this->travelTo(now()->startOfDay()->addHours(10));
        $this->camera = Camera::create(['name' => 'Test camera', 'api_key_hash' => Hash::make('test-device-key'), 'is_active' => true]);
        $section = Section::create(['name' => 'Test section', 'school_year' => '2026-2027', 'camera_id' => $this->camera->id]);
        $this->student = Student::create(['section_id' => $section->id, 'first_name' => 'Test', 'last_name' => 'Student', 'is_active' => true, 'consent_biometric' => true]);
        $this->session = AttendanceSession::create(['section_id' => $section->id, 'session_date' => today(), 'opened_at' => now()->subHours(3), 'closed_at' => now()->subHour(), 'status' => 'closed']);
    }

    private function event(string $type = 'in', array $overrides = []): array
    {
        return array_merge(['student_id' => $this->student->id, 'session_id' => $this->session->id,
            'client_uuid' => (string) Str::uuid(), 'captured_at' => now()->subHours(2)->toIso8601String(),
            'event_type' => $type, 'confidence' => 0.9], $overrides);
    }

    private function send(array $data): array
    {
        return app(RecognitionIngestService::class)->ingest($this->camera, $data);
    }

    public function test_delayed_arrival_uses_capture_time_in_closed_session(): void
    {
        $event = $this->event();
        $result = $this->send($event);
        $record = AttendanceRecord::firstOrFail();
        $this->assertTrue($record->time_in->equalTo(now()->subHours(2)));
        $this->assertTrue($result['was_offline']);
        $this->assertDatabaseCount('recognition_events', 1);
    }

    public function test_replays_after_departure_do_not_change_attendance(): void
    {
        $arrival = $this->event();
        $first = $this->send($arrival);
        $departure = $this->event('out', ['captured_at' => now()->subMinutes(90)->toIso8601String()]);
        $this->send($departure);
        $this->assertEquals($first, $this->send($arrival));
        $this->send($departure);
        $this->assertDatabaseCount('recognition_events', 2);
        $this->assertTrue(AttendanceRecord::first()->time_out->equalTo(now()->subMinutes(90)));
    }

    public function test_repeated_arrivals_do_not_become_departures(): void
    {
        $this->session->update(['closed_at' => null, 'status' => 'open']);
        $this->send($this->event());
        $this->send($this->event('in', ['captured_at' => now()->subMinutes(90)->toIso8601String()]));
        $this->assertNull(AttendanceRecord::first()->time_out);
        $this->assertTrue(AttendanceRecord::first()->time_in->equalTo(now()->subHours(2)));
    }

    public function test_auto_absence_is_corrected_by_delayed_arrival(): void
    {
        AttendanceRecord::create(['student_id' => $this->student->id, 'session_id' => $this->session->id, 'status' => 'absent', 'method' => 'manual']);
        $this->send($this->event());
        $this->assertNotEquals('absent', AttendanceRecord::first()->status);
    }

    public function test_teacher_excuse_is_preserved(): void
    {
        AttendanceRecord::create(['student_id' => $this->student->id, 'session_id' => $this->session->id, 'status' => 'excused', 'method' => 'manual']);
        $this->expectException(HttpException::class);
        $this->send($this->event());
    }

    public function test_invalid_capture_windows_and_unknown_session_are_rejected(): void
    {
        foreach ([['captured_at' => now()->addHour()->toIso8601String()],
            ['captured_at' => now()->subDays(8)->toIso8601String()],
            ['captured_at' => now()->subMinutes(30)->toIso8601String()],
            ['session_id' => 999]] as $override) {
            try {
                $this->send($this->event('in', $override));
                $this->fail('Invalid capture accepted');
            } catch (HttpException $e) {
                $this->assertEquals(422, $e->getStatusCode());
            }
        }
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_missing_consent_is_rejected(): void
    {
        $this->student->update(['consent_biometric' => false]);
        $this->expectException(HttpException::class);
        $this->send($this->event());
    }

    public function test_api_requires_device_authentication(): void
    {
        $this->postJson('/api/v1/attendance/recognitions', $this->event())->assertUnauthorized();
    }

    public function test_api_accepts_and_replays_delayed_events(): void
    {
        $event = $this->event();
        $headers = ['X-Camera-Id' => $this->camera->id, 'X-Device-Key' => 'test-device-key'];
        $first = $this->postJson('/api/v1/attendance/recognitions', $event, $headers)
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.was_offline', true);
        $this->postJson('/api/v1/attendance/recognitions', $event, $headers)
            ->assertOk()->assertExactJson($first->json());
        $this->assertDatabaseCount('recognition_events', 1);
    }

    public function test_wrong_camera_is_rejected(): void
    {
        $other = Camera::create(['name' => 'Other', 'api_key_hash' => 'unused', 'is_active' => true]);
        $this->expectException(HttpException::class);
        app(RecognitionIngestService::class)->ingest($other, $this->event());
    }

    public function test_reusing_uuid_for_another_event_is_rejected(): void
    {
        $event = $this->event();
        $this->send($event);
        $event['event_type'] = 'out';
        $this->expectException(HttpException::class);
        $this->send($event);
    }

    public function test_delayed_notification_contains_original_time_and_is_not_duplicated(): void
    {
        $role = Role::firstOrCreate(['name' => 'parent']);
        $user = User::factory()->create(['role_id' => $role->id, 'username' => 'offline-parent']);
        $guardian = Guardian::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Parent', 'notify_pref' => 'push']);
        $guardian->students()->attach($this->student->id);
        $event = $this->event();
        $this->send($event);
        $this->send($event);
        $this->assertDatabaseCount('notifications', 1);
        $notification = Notification::first();
        $this->assertStringContainsString('delayed', $notification->body);
        $this->assertStringContainsString('8:00 AM', $notification->body);
    }

    public function test_device_gets_bounded_session_cache(): void
    {
        $this->session->update(['status' => 'open', 'closed_at' => null]);
        $this->getJson('/api/v1/attendance/sessions/open', ['X-Camera-Id' => $this->camera->id, 'X-Device-Key' => 'test-device-key'])
            ->assertOk()->assertJsonPath('data.sessions.0.id', $this->session->id)
            ->assertJsonPath('data.sessions.0.student_ids.0', $this->student->id)
            ->assertJsonStructure(['data' => ['sessions' => [['expected_close_at']]]]);
    }

    public function test_session_close_then_offline_arrival_corrects_auto_absence(): void
    {
        $this->session->update(['status' => 'open', 'closed_at' => null]);
        app(\App\Services\AttendanceService::class)->closeSession($this->session);
        $this->assertEquals('absent', AttendanceRecord::first()->status);
        $this->send($this->event());
        $this->assertTrue(AttendanceRecord::first()->time_in->equalTo(now()->subHours(2)));
        $this->assertTrue(AttendanceRecord::first()->time_out->equalTo(now()));
    }

    public function test_departure_before_arrival_is_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->send($this->event('out'));
    }

    public function test_capture_cannot_be_assigned_to_reopened_session(): void
    {
        $later = AttendanceSession::create(['section_id' => $this->student->section_id, 'session_date' => today(),
            'opened_at' => now()->subMinutes(30), 'status' => 'open']);
        $this->expectException(HttpException::class);
        $this->send($this->event('in', ['session_id' => $later->id]));
    }

    public function test_shared_camera_does_not_receive_another_cameras_roster(): void
    {
        $other = Camera::create(['name' => 'Shared camera', 'api_key_hash' => Hash::make('other-key'), 'is_active' => true]);
        $this->session->update(['status' => 'open', 'closed_at' => null]);
        $this->getJson('/api/v1/attendance/sessions/open', ['X-Camera-Id' => $other->id, 'X-Device-Key' => 'other-key'])
            ->assertOk()->assertJsonCount(0, 'data.sessions');
    }
}
