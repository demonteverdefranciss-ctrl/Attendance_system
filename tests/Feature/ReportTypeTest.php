<?php

namespace Tests\Feature;

use App\Models\AttendanceExcuseRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');

        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->actingAs(User::factory()->create([
            'role_id' => $role->id,
            'username' => 'report-type-admin',
        ]));
    }

    public function test_present_late_and_absent_report_types_filter_records_and_exports(): void
    {
        $section = Section::create([
            'name' => 'Report class',
            'grade_level' => 'Grade 1',
            'school_year' => '2026',
        ]);
        $student = Student::create([
            'section_id' => $section->id,
            'first_name' => 'Report',
            'last_name' => 'Student',
        ]);
        foreach (['late', 'absent', 'present'] as $status) {
            $session = AttendanceSession::create([
                'section_id' => $section->id,
                'session_date' => today(),
                'opened_at' => now(),
                'status' => 'closed',
            ]);
            AttendanceRecord::create([
                'session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $status,
                'method' => 'manual',
            ]);
        }

        $this->get(route('reports.index', ['report_type' => 'late']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.report_type', 'late')
                ->has('records.data', 1)
                ->where('records.data.0.status', 'late'));

        $this->get(route('reports.index', ['report_type' => 'present']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.report_type', 'present')
                ->has('records.data', 1)
                ->where('records.data.0.status', 'present'));

        $this->get(route('reports.preview', ['format' => 'csv', 'report_type' => 'absent']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reportType', 'absent')
                ->has('records', 1)
                ->where('records.0.status', 'absent'));

        $response = $this->get(route('reports.csv', ['report_type' => 'late']));
        $response->assertOk();
        $this->assertStringContainsString('late', $response->streamedContent());
        $this->assertStringNotContainsString('present', $response->streamedContent());

        foreach (['present', 'late', 'absent'] as $reportType) {
            $this->assertPdfDownload($reportType);
        }
    }

    public function test_warning_report_lists_only_formal_three_consecutive_absence_warnings(): void
    {
        $section = Section::create([
            'name' => 'Warning class',
            'grade_level' => 'Grade 2',
            'school_year' => '2026',
        ]);
        $student = Student::create([
            'section_id' => $section->id,
            'first_name' => 'Absent',
            'last_name' => 'Student',
        ]);

        AttendanceExcuseRequest::create([
            'student_id' => $student->id,
            'streak_count' => 3,
            'attendance_record_ids' => [11, 12, 13],
            'streak_summary' => [
                ['record_id' => 13, 'date' => today()->toDateString(), 'status' => 'absent'],
                ['record_id' => 12, 'date' => today()->subDay()->toDateString(), 'status' => 'absent'],
                ['record_id' => 11, 'date' => today()->subDays(2)->toDateString(), 'status' => 'absent'],
            ],
            'status' => 'awaiting_letter',
        ]);
        AttendanceExcuseRequest::create([
            'student_id' => $student->id,
            'streak_count' => 1,
            'attendance_record_ids' => [14],
            'streak_summary' => [
                ['record_id' => 14, 'date' => today()->toDateString(), 'status' => 'absent'],
            ],
            'status' => 'awaiting_letter',
        ]);

        $this->get(route('reports.index', ['report_type' => 'warnings']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.report_type', 'warnings')
                ->has('warnings', 1)
                ->where('warnings.0.streak_count', 3)
                ->where('warnings.0.student', 'Student, Absent')
                ->where('warnings.0.status', 'Awaiting Letter'));

        $response = $this->get(route('reports.csv', ['report_type' => 'warnings']));
        $response->assertOk();
        $this->assertStringContainsString('Consecutive Absences', $response->streamedContent());
        $this->assertStringContainsString('Student, Absent', $response->streamedContent());
        $this->assertPdfDownload('warnings');
    }

    public function test_teacher_warning_reports_are_limited_to_their_sections(): void
    {
        $role = Role::firstOrCreate(['name' => 'teacher']);
        $teacherUser = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'report-type-teacher',
        ]);
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'first_name' => 'Report',
            'last_name' => 'Teacher',
        ]);
        $ownedSection = Section::create([
            'name' => 'Teacher class',
            'grade_level' => 'Grade 3',
            'school_year' => '2026',
            'adviser_id' => $teacher->id,
        ]);
        $otherSection = Section::create([
            'name' => 'Other class',
            'grade_level' => 'Grade 3',
            'school_year' => '2026',
        ]);

        foreach ([$ownedSection, $otherSection] as $index => $section) {
            $student = Student::create([
                'section_id' => $section->id,
                'first_name' => 'Student',
                'last_name' => $index === 0 ? 'Owned' : 'Outside',
            ]);
            AttendanceExcuseRequest::create([
                'student_id' => $student->id,
                'streak_count' => 3,
                'attendance_record_ids' => [21, 22, 23],
                'streak_summary' => [
                    ['record_id' => 23, 'date' => today()->toDateString(), 'status' => 'absent'],
                ],
                'status' => 'awaiting_letter',
            ]);
        }

        $this->actingAs($teacherUser)
            ->get(route('reports.index', ['report_type' => 'warnings']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('warnings', 1)
                ->where('warnings.0.student', 'Owned, Student'));

        $this->get(route('reports.index', [
            'report_type' => 'warnings',
            'section_id' => $otherSection->id,
        ]))->assertForbidden();
    }

    public function test_unknown_report_type_is_rejected(): void
    {
        $this->get(route('reports.index', ['report_type' => 'all_the_data']))
            ->assertSessionHasErrors('report_type');
    }

    private function assertPdfDownload(string $reportType): void
    {
        $response = $this->get(route('reports.pdf', ['report_type' => $reportType]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
