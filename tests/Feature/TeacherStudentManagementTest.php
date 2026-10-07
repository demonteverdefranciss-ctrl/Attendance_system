<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_with_edit_permission_can_update_student_in_assigned_section(): void
    {
        [$teacherUser, $student] = $this->teacherAndStudent(true);

        $this->actingAs($teacherUser)
            ->get(route('teacher.students.edit', $student))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Students/Form')
                ->where('student.id', $student->id));

        $this->actingAs($teacherUser)->put(route('teacher.students.update', $student), [
            'first_name' => 'Updated',
            'last_name' => 'Student',
            'lrn' => '123456789012',
            'gender' => 'female',
            'birthdate' => '2015-04-12',
            'section_id' => $student->section_id,
        ])->assertRedirect(route('teacher.students.index'));

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Updated',
            'lrn' => '123456789012',
        ]);
    }

    public function test_teacher_without_edit_permission_cannot_edit_student(): void
    {
        [$teacherUser, $student] = $this->teacherAndStudent(false);

        $this->actingAs($teacherUser)->get(route('teacher.students.edit', $student))->assertForbidden();
        $this->actingAs($teacherUser)->put(route('teacher.students.update', $student), [
            'first_name' => 'Updated',
            'last_name' => 'Student',
            'section_id' => $student->section_id,
        ])->assertForbidden();
    }

    public function test_teacher_cannot_edit_student_outside_assigned_sections(): void
    {
        [$teacherUser] = $this->teacherAndStudent(true);
        $otherSection = Section::create([
            'name' => 'Other',
            'grade_level' => 'Grade 5',
            'school_year' => '2026-2027',
        ]);
        $student = Student::create([
            'section_id' => $otherSection->id,
            'first_name' => 'Other',
            'last_name' => 'Student',
            'is_active' => true,
        ]);

        $this->actingAs($teacherUser)->get(route('teacher.students.edit', $student))->assertForbidden();
    }

    private function teacherAndStudent(bool $canEditStudents): array
    {
        $role = Role::firstOrCreate(['name' => 'teacher']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'teacher-' . uniqid(),
            'is_active' => true,
        ]);
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'first_name' => 'Tina',
            'last_name' => 'Teacher',
            'can_edit_students' => $canEditStudents,
        ]);
        $section = Section::create([
            'adviser_id' => $teacher->id,
            'name' => 'Mabini',
            'grade_level' => 'Grade 6',
            'school_year' => '2026-2027',
        ]);
        $student = Student::create([
            'section_id' => $section->id,
            'first_name' => 'Ana',
            'last_name' => 'Student',
            'is_active' => true,
        ]);

        return [$user, $student];
    }
}
