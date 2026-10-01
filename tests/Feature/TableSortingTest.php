<?php

namespace Tests\Feature;

use App\Models\{Role, User, Student, Section, AttendanceSession, AttendanceRecord};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TableSortingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'username' => 'sort-admin']));
    }

    public function test_student_sorting_happens_before_pagination_and_rejects_unknown_keys(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Student::create(['first_name' => 'Student', 'last_name' => sprintf('Name%02d', $i)]);
        }
        $this->get(route('admin.students.index', ['sort' => 'name', 'direction' => 'desc']))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('students.data.0.last_name', 'Name25')->where('students.total', 25));
        $this->get(route('admin.students.index', ['sort' => 'name', 'direction' => 'desc', 'page' => 2]))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('students.data.0.last_name', 'Name05'));
        $this->get(route('admin.students.index', ['sort' => 'invalid sql', 'direction' => 'desc']))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('students.data.0.last_name', 'Name01'));
    }

    public function test_all_admin_sort_columns_are_valid_queries(): void
    {
        $user = User::where('username', 'sort-admin')->firstOrFail();
        $teacher = \App\Models\Teacher::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Teacher']);
        \App\Models\Guardian::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Guardian']);
        $camera = \App\Models\Camera::create(['name' => 'Test camera', 'api_key_hash' => 'test']);
        $section = Section::create(['name' => 'Test', 'grade_level' => 'Grade 1', 'school_year' => '2026', 'adviser_id' => $teacher->id, 'camera_id' => $camera->id]);
        Student::create(['first_name' => 'Test', 'last_name' => 'Student', 'section_id' => $section->id]);
        \App\Models\Schedule::create(['section_id' => $section->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00', 'type' => 'am', 'is_active' => true]);
        $tables = [
            'students' => ['name', 'lrn', 'section', 'gender', 'consent'],
            'teachers' => ['name', 'employee_no', 'username', 'email', 'status'],
            'guardians' => ['name', 'username', 'phone', 'notify_pref', 'students_count'],
            'sections' => ['name', 'school_year', 'adviser', 'camera', 'students_count', 'session_max_minutes'],
            'schedules' => ['section', 'day', 'time', 'late_after', 'type', 'is_active'],
            'cameras' => ['name', 'location', 'sections', 'status', 'id'],
            'audit-logs' => ['created_at', 'user', 'action', 'entity', 'ip_address'],
        ];
        foreach ($tables as $table => $keys) {
            foreach ($keys as $key) {
                $this->get(route("admin.$table.index", ['sort' => $key, 'direction' => 'desc']))->assertOk();
            }
        }
        foreach (['students', 'teachers', 'guardians', 'sections', 'cameras', 'schedules', 'no-class-days'] as $category) {
            $this->get(route('admin.archive.index', ['category' => $category, 'sort' => 'title']))->assertOk();
        }
    }

    public function test_report_tables_sort_independently_and_export_uses_sort(): void
    {
        $section = Section::create(['name' => 'Test', 'grade_level' => 'Grade 1', 'school_year' => '2026']);
        $student = Student::create(['first_name' => 'Test', 'last_name' => 'Student', 'section_id' => $section->id]);
        foreach ([1, 2] as $days) {
            $session = AttendanceSession::create(['section_id' => $section->id, 'session_date' => today()->subDays($days), 'opened_at' => now(), 'status' => 'closed']);
            AttendanceRecord::create(['session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'method' => 'manual']);
        }
        $params = ['records_sort' => 'date', 'records_direction' => 'asc', 'sessions_sort' => 'date', 'sessions_direction' => 'desc'];
        $this->get(route('reports.index', $params))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('records.data.0.date', today()->subDays(2)->toDateString())
            ->where('sessions.data.0.session_date', today()->subDay()->toDateString()));
        $this->get(route('reports.preview', [...$params, 'format' => 'csv']))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('records.0.date', today()->subDays(2)->toDateString())->where('filters.records_sort', 'date'));
    }

    public function test_rename_only_changes_main_admin_display_name(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $user = User::factory()->create(['username' => 'admin', 'role_id' => $role->id, 'name' => 'Old name']);
        $password = $user->password;
        $migration = require database_path('migrations/2026_10_01_000001_update_main_admin_display_name.php');
        $migration->up();
        $this->assertSame('Celenia A. Molinyawe', $user->fresh()->name);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseMissing('users', ['username' => 'sort-admin', 'name' => 'Celenia A. Molinyawe']);
    }
}
