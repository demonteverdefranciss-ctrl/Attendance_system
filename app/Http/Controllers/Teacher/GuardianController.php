<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\InputRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function create(Request $request): Response
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_add_parents, 403);

        $students = $this->studentsFor($teacher)->get(['id', 'first_name', 'last_name', 'lrn', 'section_id']);

        return Inertia::render('Teacher/Parents/Form', ['students' => $students]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_add_parents, 403);

        $data = $request->validate([
            'first_name' => InputRules::personName(),
            'last_name' => InputRules::personName(),
            'phone' => InputRules::phone(),
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')],
            'relationship' => ['nullable', 'string', 'max:50'],
        ], InputRules::messages());

        $student = $this->studentsFor($teacher)->whereKey($data['student_id'])->firstOrFail();

        DB::transaction(function () use ($data, $student) {
            $user = User::create([
                'role_id' => Role::where('name', 'parent')->value('id'),
                'username' => $data['username'],
                'name' => "{$data['first_name']} {$data['last_name']}",
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $guardian = $user->guardian()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'notify_pref' => 'push',
            ]);

            $isPrimary = ! $student->guardians()->wherePivot('is_primary', true)->exists();
            $guardian->students()->attach($student->id, [
                'relationship' => $data['relationship'] ?? null,
                'is_primary' => $isPrimary,
            ]);
        });

        return redirect()->route('teacher.students.index')->with('success', 'Parent account created and linked to the student.');
    }

    private function teacher(Request $request): Teacher
    {
        return $request->user()->teacher()->firstOrFail();
    }

    private function studentsFor(Teacher $teacher)
    {
        return Student::query()
            ->where('is_active', true)
            ->whereIn('section_id', $teacher->sections()->select('id'))
            ->orderBy('last_name')
            ->orderBy('first_name');
    }
}