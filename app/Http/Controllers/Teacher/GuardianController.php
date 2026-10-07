<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\InputRules;
use App\Support\SoftDeleteUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $this->teacher($request);
        $guardians = $this->guardiansFor($teacher)
            ->with('user:id,username,email,is_active')
            ->with(['students' => fn ($query) => $query
                ->whereIn('students.section_id', $teacher->sections()->select('sections.id'))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->select(['students.id', 'first_name', 'last_name', 'section_id'])])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Teacher/Parents/Index', [
            'guardians' => $guardians,
            'canAddParents' => (bool) $teacher->can_add_parents,
            'canEditParents' => (bool) $teacher->can_edit_parents,
            'canArchiveParents' => (bool) $teacher->can_archive_parents,
        ]);
    }

    public function edit(Request $request, Guardian $guardian): Response
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_edit_parents, 403);

        $guardian = $this->guardiansFor($teacher)->whereKey($guardian->id)->firstOrFail();
        $guardian->load('user:id,username,email');
        $guardian->setRelation('students', $guardian->students()
            ->whereIn('students.section_id', $teacher->sections()->select('sections.id'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['students.id', 'first_name', 'last_name']));

        return Inertia::render('Teacher/Parents/Form', ['guardian' => $guardian]);
    }

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

        return redirect()->route('teacher.parents.index')->with('success', 'Parent account created and linked to the student.');
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_edit_parents, 403);
        $guardian = $this->guardiansFor($teacher)->whereKey($guardian->id)->firstOrFail();
        $user = $guardian->user;
        abort_unless($user, 404);

        if ($request->input('password') === '') {
            $request->merge(['password' => null]);
        }

        $data = $request->validate([
            'first_name' => InputRules::personName(),
            'last_name' => InputRules::personName(),
            'phone' => InputRules::phone(),
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults()],
        ], InputRules::messages());

        DB::transaction(function () use ($data, $guardian, $user) {
            $guardian->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
            ]);

            $user->update(array_filter([
                'username' => $data['username'],
                'name' => "{$data['first_name']} {$data['last_name']}",
                'email' => $data['email'] ?? null,
                'password' => $data['password'] ?? null,
            ], fn ($value) => $value !== null));
        });

        return redirect()->route('teacher.parents.index')->with('success', 'Parent account updated successfully.');
    }

    public function destroy(Request $request, Guardian $guardian): RedirectResponse
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_archive_parents, 403);
        $guardian = $this->guardiansFor($teacher)->whereKey($guardian->id)->firstOrFail();

        DB::transaction(function () use ($guardian) {
            $user = $guardian->user;
            if ($user) {
                SoftDeleteUnique::archive($user, ['username', 'email']);
                $user->update(['is_active' => false]);
                $guardian->delete();
                $user->delete();
            } else {
                $guardian->delete();
            }
        });

        return redirect()->route('teacher.parents.index')->with('success', 'Parent account moved to archive.');
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

    private function guardiansFor(Teacher $teacher)
    {
        return Guardian::query()->whereHas('students', fn ($query) =>
            $query->whereIn('students.section_id', $teacher->sections()->select('sections.id')),
        );
    }
}