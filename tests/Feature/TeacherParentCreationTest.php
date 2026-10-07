<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherParentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permitted_teacher_creates_parent_linked_to_assigned_student(): void
    {
        [$teacherUser, $section] = $this->teacherWithPermission(true);
        $student = $this->studentIn($section);

        $this->actingAs($teacherUser)->post(route('teacher.parents.store'), [
            'first_name' => 'Marta',
            'last_name' => 'Cruz',
            'username' => 'marta-parent',
            'email' => 'marta@example.test',
            'phone' => '09171234567',
            'password' => 'Parent@2026',
            'student_id' => $student->id,
            'relationship' => 'mother',
        ])->assertRedirect(route('teacher.students.index'));

        $parent = User::where('username', 'marta-parent')->firstOrFail()->guardian;
        $this->assertDatabaseHas('student_guardian', [
            'student_id' => $student->id,
            'guardian_id' => $parent->id,
            'relationship' => 'mother',
            'is_primary' => true,
        ]);
    }

    public function test_teacher_without_permission_cannot_open_parent_creation(): void
    {
        [$teacherUser] = $this->teacherWithPermission(false);

        $this->actingAs($teacherUser)->get(route('teacher.parents.create'))->assertForbidden();
    }

    public function test_teacher_cannot_link_parent_to_student_outside_assigned_sections(): void
    {
        [$teacherUser] = $this->teacherWithPermission(true);
        $otherSection = Section::create([
            'name' => 'Other',
            'grade_level' => 'Grade 5',
            'school_year' => '2026-2027',
        ]);
        $student = $this->studentIn($otherSection);

        $this->actingAs($teacherUser)->post(route('teacher.parents.store'), [
            'first_name' => 'Marta',
            'last_name' => 'Cruz',
            'username' => 'outside-parent',
            'password' => 'Parent@2026',
            'student_id' => $student->id,
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['username' => 'outside-parent']);
    }

    private function teacherWithPermission(bool $canAddParents): array
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
            'can_add_parents' => $canAddParents,
        ]);
        $section = Section::create([
            'adviser_id' => $teacher->id,
            'name' => 'Mabini',
            'grade_level' => 'Grade 6',
            'school_year' => '2026-2027',
        ]);

        return [$user, $section];
    }

    private function studentIn(Section $section): Student
    {
        return Student::create([
            'section_id' => $section->id,
            'first_name' => 'Ana',
            'last_name' => 'Student',
            'is_active' => true,
        ]);
    }
}