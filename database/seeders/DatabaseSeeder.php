<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed roles, a default admin, and a small sample dataset for testing.
     * Uses the query builder (not Eloquent models) so it runs in Phase 1
     * before the application models exist.
     */
    public function run(): void
    {
        $now = now();

        // --- Roles ---
        $roleIds = [];
        foreach ([
            'admin'   => 'System administrator',
            'teacher' => 'Class adviser',
            'parent'  => 'Parent or guardian',
        ] as $name => $description) {
            $roleIds[$name] = DB::table('roles')->insertGetId([
                'name' => $name, 'description' => $description,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // --- Default admin (CHANGE THIS PASSWORD AFTER FIRST LOGIN) ---
        DB::table('users')->insert([
            'role_id' => $roleIds['admin'], 'username' => 'admin',
            'name' => 'System Administrator', 'email' => 'admin@bigaaes.edu.ph',
            'password' => Hash::make('Admin@123'), 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // --- Sample teacher (user + profile) ---
        $teacherUserId = DB::table('users')->insertGetId([
            'role_id' => $roleIds['teacher'], 'username' => 'teacher01',
            'name' => 'Maria Cruz', 'email' => 'maria.cruz@bigaaes.edu.ph',
            'password' => Hash::make('Teacher@123'), 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $teacherId = DB::table('teachers')->insertGetId([
            'user_id' => $teacherUserId, 'employee_no' => 'T-0001',
            'first_name' => 'Maria', 'last_name' => 'Cruz',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // --- Sample section advised by that teacher ---
        $sectionId = DB::table('sections')->insertGetId([
            'adviser_id' => $teacherId, 'name' => 'Mabini',
            'grade_level' => 'Grade 6', 'school_year' => '2026-2027',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // --- Sample guardian (user + profile) ---
        $guardianUserId = DB::table('users')->insertGetId([
            'role_id' => $roleIds['parent'], 'username' => 'parent01',
            'name' => 'Jose Santos', 'email' => null,
            'password' => Hash::make('Parent@123'), 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $guardianId = DB::table('guardians')->insertGetId([
            'user_id' => $guardianUserId, 'first_name' => 'Jose', 'last_name' => 'Santos',
            'phone' => '09171234567', 'notify_pref' => 'push',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // --- Sample students; link the first to the guardian ---
        $students = [
            ['Ana',   'Santos',     '136000000001'],
            ['Ben',   'Reyes',      '136000000002'],
            ['Carlo', 'Dela Cruz',  '136000000003'],
        ];
        foreach ($students as $i => [$first, $last, $lrn]) {
            $studentId = DB::table('students')->insertGetId([
                'section_id' => $sectionId, 'lrn' => $lrn,
                'first_name' => $first, 'last_name' => $last,
                'consent_biometric' => false, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($i === 0) {
                DB::table('student_guardian')->insert([
                    'student_id' => $studentId, 'guardian_id' => $guardianId,
                    'relationship' => 'father', 'is_primary' => true,
                ]);
            }
        }

        // --- Demo recognition camera (CHANGE THE DEVICE KEY IN PRODUCTION) ---
        // Device key (plaintext): demo-device-key-12345
        DB::table('cameras')->insert([
            'name' => 'Main Entrance', 'location' => 'School Gate', 'rtsp_url' => null,
            'api_key_hash' => Hash::make('demo-device-key-12345'), 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
