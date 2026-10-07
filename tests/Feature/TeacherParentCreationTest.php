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
        ])->assertRedirect(route('teacher.parents.index'));

        $parent = User::where('username', 'marta-parent')->firstOrFail()->guardian;
        $this->assertDatabaseHas('student_guardian', [
            'student_id' => $student->id,
            'guardian_id' => $parent->id,
            'relationship' => 'mother',
            'is_primary' => true,
        ]);

        $this->actingAs($teacherUser)->get(route('teacher.parents.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Parents/Index')
                ->where('guardians.data.0.first_name', 'Marta')
                ->where('guardians.data.0.students.0.first_name', 'Ana'));
    }

    public function test_teacher_without_permission_cannot_open_parent_creation(): void
    {
        [$teacherUser] = $this->teacherWithPermission(false);

        $this->actingAs($teacherUser)->get(route('teacher.parents.create'))->assertForbidden();
        $this->actingAs($teacherUser)->get(route('teacher.parents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teacher/Parents/Index')
                ->where('canAddParents', false)
                ->where('canEditParents', false)
                ->where('canArchiveParents', false));
    }

    public function test_teacher_without_edit_or_archive_permission_cannot_manage_parent_accounts(): void
    {
        [$teacherUser, $section] = $this->teacherWithPermission(false);
        $student = $this->studentIn($section);
        $guardian = $this->guardianLinkedTo($student);

        $this->actingAs($teacherUser)
            ->get(route('teacher.parents.edit', $guardian))
            ->assertForbidden();
        $this->actingAs($teacherUser)
            ->delete(route('teacher.parents.destroy', $guardian))
            ->assertForbidden();
    }

    public function test_teacher_with_edit_and_archive_permissions_can_manage_parent_account(): void
    {
        [$teacherUser, $section] = $this->teacherWithPermissions(true, true, true);
        $student = $this->studentIn($section);
        $guardian = $this->guardianLinkedTo($student);

        $this->actingAs($teacherUser)->put(route('teacher.parents.update', $guardian), [
            'first_name' => 'Updated',
            'last_name' => 'Guardian',
            'phone' => '09171234567',
            'username' => 'updated-parent',
            'email' => 'updated@example.test',
            'password' => '',
        ])->assertRedirect(route('teacher.parents.index'));

        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'first_name' => 'Updated']);
        $this->assertDatabaseHas('users', ['id' => $guardian->user_id, 'username' => 'updated-parent']);

        $this->actingAs($teacherUser)->delete(route('teacher.parents.destroy', $guardian))
            ->assertRedirect(route('teacher.parents.index'));

        $this->assertSoftDeleted('guardians', ['id' => $guardian->id]);
        $this->assertSoftDeleted('users', ['id' => $guardian->user_id]);
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
        return $this->teacherWithPermissions($canAddParents, false, false);
    }

    private function teacherWithPermissions(bool $canAddParents, bool $canEditParents, bool $canArchiveParents): array
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
            'can_edit_parents' => $canEditParents,
            'can_archive_parents' => $canArchiveParents,
        ]);
        $section = Section::create([
            'adviser_id' => $teacher->id,
            'name' => 'Mabini',
            'grade_level' => 'Grade 6',
            'school_year' => '2026-2027',
        ]);

        return [$user, $section];
    }

    private function guardianLinkedTo(Student $student): \App\Models\Guardian
    {
        $parentRole = Role::firstOrCreate(['name' => 'parent']);
        $user = User::factory()->create([
            'role_id' => $parentRole->id,
            'username' => 'parent-' . uniqid(),
            'is_active' => true,
        ]);
        $guardian = $user->guardian()->create([
            'first_name' => 'Marta',
            'last_name' => 'Cruz',
            'phone' => null,
            'notify_pref' => 'push',
        ]);
        $guardian->students()->attach($student->id, ['relationship' => 'guardian', 'is_primary' => true]);

        return $guardian;
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