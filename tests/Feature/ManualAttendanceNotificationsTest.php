<?php

namespace Tests\Feature;

use App\Models\{AttendanceSession, Guardian, Notification, Role, Section, Student, User};
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualAttendanceNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceSession $session;
    private Student $student;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $parentRole = Role::firstOrCreate(['name' => 'parent']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);
        $parentUser = User::factory()->create([
            'role_id' => $parentRole->id,
            'username' => 'attendance-notification-parent',
        ]);
        $guardian = Guardian::create([
            'user_id' => $parentUser->id,
            'first_name' => 'Test',
            'last_name' => 'Parent',
            'notify_pref' => 'push',
        ]);
        $this->teacher = User::factory()->create([
            'role_id' => $teacherRole->id,
            'username' => 'attendance-notification-teacher',
        ]);

        $section = Section::create([
            'name' => 'Notification test',
            'school_year' => '2026-2027',
        ]);
        $this->student = Student::create([
            'section_id' => $section->id,
            'first_name' => 'Test',
            'last_name' => 'Student',
        ]);
        $guardian->students()->attach($this->student->id);

        $this->session = AttendanceSession::create([
            'section_id' => $section->id,
            'session_date' => today(),
            'opened_at' => now()->subHours(2),
            'status' => 'open',
        ]);
    }

    public function test_manual_present_and_early_time_out_create_parent_notifications_once(): void
    {
        $attendance = app(AttendanceService::class);
        $record = $attendance->mark($this->session, $this->student->id, 'present', [
            'marked_by' => $this->teacher->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'guardian_id' => $this->student->guardians()->first()->pivot->guardian_id,
            'student_id' => $this->student->id,
            'type' => 'arrival',
            'title' => 'Student Arrival',
            'status' => 'pending',
        ]);

        $timeOut = now()->subHour();
        $attendance->recordTimeOut($this->session, $this->student->id, $timeOut, [
            'marked_by' => $this->teacher->id,
        ]);
        $attendance->recordTimeOut($this->session, $this->student->id, $timeOut, [
            'marked_by' => $this->teacher->id,
        ]);

        $this->assertSame(2, Notification::where('student_id', $this->student->id)->count());
        $timeOutNotification = Notification::where('type', 'early_time_out')->firstOrFail();
        $this->assertSame('Early Time-out Recorded', $timeOutNotification->title);
        $this->assertStringContainsString('signed out early', $timeOutNotification->body);
        $this->assertSame($record->id, $timeOutNotification->payload['attendance_record_id']);
        $this->assertSame('pending', $timeOutNotification->status);
    }

    public function test_attendance_notification_remains_in_parent_center_when_push_is_disabled(): void
    {
        $guardian = $this->student->guardians()->first();
        $guardian->update(['notify_pref' => 'none']);

        app(AttendanceService::class)->mark($this->session, $this->student->id, 'present', [
            'marked_by' => $this->teacher->id,
        ]);

        $notification = Notification::where('student_id', $this->student->id)->firstOrFail();
        $this->assertSame('arrival', $notification->type);
        $this->assertSame('sent', $notification->status);
        $this->assertNotNull($notification->sent_at);
    }
}
