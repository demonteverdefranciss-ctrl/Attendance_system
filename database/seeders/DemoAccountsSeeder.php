<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Safe to run multiple times — creates or refreshes demo accounts for CRUD/UAT testing.
 *
 * Run: php artisan db:seed --class=DemoAccountsSeeder
 */
class DemoAccountsSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Crud@123';

    public function run(): void
    {
        $roles = Role::pluck('id', 'name');

        if ($roles->count() < 3) {
            $this->command?->error('Roles table is empty. Run DatabaseSeeder first.');

            return;
        }

        $this->upsertUser(
            username: 'crud_admin',
            roleId: $roles['admin'],
            name: 'CRUD Demo Admin',
            email: 'crud.admin@bigaaes.edu.ph',
        );

        $teacherUser = $this->upsertUser(
            username: 'crud_teacher',
            roleId: $roles['teacher'],
            name: 'CRUD Demo Teacher',
            email: 'crud.teacher@bigaaes.edu.ph',
        );

        $teacher = Teacher::updateOrCreate(
            ['user_id' => $teacherUser->id],
            [
                'employee_no' => 'T-CRUD-01',
                'first_name' => 'CRUD',
                'last_name' => 'Teacher',
                'phone' => '09170000001',
            ]
        );

        $section = Section::updateOrCreate(
            ['name' => 'CRUD Demo Section', 'school_year' => '2026-2027'],
            [
                'adviser_id' => $teacher->id,
                'grade_level' => 'Grade 6',
            ]
        );

        $parentUser = $this->upsertUser(
            username: 'crud_parent',
            roleId: $roles['parent'],
            name: 'CRUD Demo Parent',
            email: 'crud.parent@bigaaes.edu.ph',
        );

        $guardian = Guardian::updateOrCreate(
            ['user_id' => $parentUser->id],
            [
                'first_name' => 'CRUD',
                'last_name' => 'Parent',
                'phone' => '09170000002',
                'notify_pref' => 'push',
            ]
        );

        $demoStudent = Student::updateOrCreate(
            ['lrn' => '136009990001'],
            [
                'section_id' => $section->id,
                'first_name' => 'CRUD',
                'last_name' => 'Student',
                'gender' => 'female',
                'consent_biometric' => true,
                'is_active' => true,
            ]
        );

        $guardian->students()->syncWithoutDetaching([
            $demoStudent->id => ['relationship' => 'mother', 'is_primary' => true],
        ]);

        Student::updateOrCreate(
            ['lrn' => '136009990002'],
            [
                'section_id' => $section->id,
                'first_name' => 'Sample',
                'last_name' => 'Pupil',
                'gender' => 'male',
                'consent_biometric' => false,
                'is_active' => true,
            ]
        );

        $this->command?->info('Demo CRUD accounts are ready.');
        $this->command?->table(
            ['Role', 'Username', 'Password', 'What you can do'],
            [
                ['Admin', 'crud_admin', self::DEMO_PASSWORD, 'CRUD teachers, students, parents, sections, schedules'],
                ['Teacher', 'crud_teacher', self::DEMO_PASSWORD, 'Attendance marking, enrollment request approval'],
                ['Parent', 'crud_parent', self::DEMO_PASSWORD, 'View children, notifications, submit LRN requests'],
            ]
        );
        $this->command?->warn('Students do not have login accounts — manage them as Admin → Students.');
        $this->command?->line("Demo student LRN for parent enrollment test: {$demoStudent->lrn}");
    }

    private function upsertUser(string $username, int $roleId, string $name, ?string $email): User
    {
        return User::updateOrCreate(
            ['username' => $username],
            [
                'role_id' => $roleId,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_active' => true,
            ]
        );
    }
}
