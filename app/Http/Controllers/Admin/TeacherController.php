<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(): Response
    {
        $teachers = Teacher::with('user:id,username,email,is_active')
            ->orderBy('last_name')
            ->get();

        return Inertia::render('Admin/Teachers/Index', ['teachers' => $teachers]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Teachers/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'role_id' => Role::where('name', 'teacher')->value('id'),
                'username' => $data['username'],
                'name' => "{$data['first_name']} {$data['last_name']}",
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->teacher()->create([
                'employee_no' => $data['employee_no'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
            ]);
        });

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher added successfully.');
    }

    public function edit(Teacher $teacher): Response
    {
        $teacher->load('user:id,username,email');

        return Inertia::render('Admin/Teachers/Form', ['teacher' => $teacher]);
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $this->validateData($request, $teacher);

        DB::transaction(function () use ($data, $teacher) {
            $teacher->update([
                'employee_no' => $data['employee_no'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
            ]);

            $teacher->user->update(array_filter([
                'username' => $data['username'],
                'name' => "{$data['first_name']} {$data['last_name']}",
                'email' => $data['email'] ?? null,
                'password' => $data['password'] ?? null,
            ], fn ($v) => $v !== null));
        });

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher updated successfully.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        // Deleting the user cascades to the teacher profile.
        $teacher->user->delete();

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Teacher $teacher = null): array
    {
        $userId = $teacher?->user_id;

        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'employee_no' => ['nullable', 'string', 'max:50', Rule::unique('teachers', 'employee_no')->ignore($teacher?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$teacher ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }
}
