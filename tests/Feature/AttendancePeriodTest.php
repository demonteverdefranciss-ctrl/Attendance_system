<?php

namespace Tests\Feature;

use App\Models\{AttendanceRecord, AttendanceSession, Guardian, Role, Section, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancePeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_boundaries_match_web_records_and_mobile_summary(): void
    {
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $this->travelTo(now()->setDate(2026, 3, 3)->startOfDay());
        $role = Role::firstOrCreate(['name' => 'parent']);
        $user = User::factory()->create(['role_id' => $role->id, 'username' => 'period-parent']);
        $guardian = Guardian::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Parent']);
        $section = Section::create(['name' => 'Period class', 'grade_level' => '1', 'school_year' => '2025-2026']);
        $student = Student::create(['section_id' => $section->id, 'first_name' => 'Test', 'last_name' => 'Child']);
        $other = Student::create(['section_id' => $section->id, 'first_name' => 'Other', 'last_name' => 'Child']);
        $guardian->students()->attach($student);
        foreach ([0, 6, 7, 29, 30, -1] as $days) {
            $session = AttendanceSession::create(['section_id' => $section->id, 'session_date' => today()->subDays($days), 'opened_at' => now(), 'status' => 'closed']);
            foreach ([$student, $other] as $child) {
                AttendanceRecord::create(['session_id' => $session->id, 'student_id' => $child->id, 'status' => 'present', 'method' => 'manual']);
            }
        }
        $this->actingAs($user);
        $analytics = app(\App\Services\AnalyticsService::class);
        $from = today()->subDays(6)->toDateString();
        $to = today()->toDateString();
        $this->assertSame(2, $analytics->paginatedRecentSessions([$section->id], null, $from, $to)->total());
        $this->assertSame(4, $analytics->paginatedRecords([$section->id], $from, $to)->total());
        $this->assertSame(0, $analytics->paginatedRecentSessions([0], null, $from, $to)->total());
        foreach (['week' => 2, 'month' => 4, 'all' => 6] as $period => $count) {
            $this->get(route('parent.attendance.index', ['period' => $period, 'student_id' => $student->id]))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->component('Parent/Attendance')->where('filters.period', $period)
                    ->has('records.data', $count)->where('records.total', $count));
            Sanctum::actingAs($user);
            $this->getJson("/api/v1/students/{$student->id}/attendance?period=$period")
                ->assertOk()->assertJsonCount($count, 'data');
            $this->getJson("/api/v1/analytics/student/{$student->id}?period=$period")
                ->assertOk()->assertJsonPath('data.total', $count);
        }
        $this->getJson("/api/v1/students/{$other->id}/attendance?period=week")->assertForbidden();
        $this->getJson("/api/v1/analytics/student/{$other->id}?period=month")->assertForbidden();
        $this->getJson("/api/v1/students/{$student->id}/attendance?period=invalid")->assertUnprocessable();
    }
}
