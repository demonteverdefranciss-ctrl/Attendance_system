<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function index(): Response
    {
        $guardians = Guardian::with('user:id,username,email,is_active')
            ->withCount('students')
            ->orderBy('last_name')
            ->get();

        return Inertia::render('Admin/Guardians/Index', ['guardians' => $guardians]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Guardians/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'role_id' => Role::where('name', 'parent')->value('id'),
                'username' => $data['username'],
                'name' => "{$data['first_name']} {$data['last_name']}",
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $user->guardian()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'notify_pref' => $data['notify_pref'],
            ]);
        });

        return redirect()->route('admin.guardians.index')->with('success', 'Parent/guardian added successfully.');
    }

    public function edit(Guardian $guardian): Response
    {
        $guardian->load('user:id,username,email');

        return Inertia::render('Admin/Guardians/Form', ['guardian' => $guardian]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $data = $this->validateData($request, $guardian);

        DB::transaction(function () use ($data, $guardian) {
            $guardian->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'notify_pref' => $data['notify_pref'],
            ]);

            $guardian->user->update(array_filter([
                'username' => $data['username'],
                'name' => "{$data['first_name']} {$data['last_name']}",
                'email' => $data['email'] ?? null,
                'password' => $data['password'] ?? null,
            ], fn ($v) => $v !== null));
        });

        return redirect()->route('admin.guardians.index')->with('success', 'Parent/guardian updated successfully.');
    }

    public function destroy(Guardian $guardian): RedirectResponse
    {
        $guardian->user->delete();

        return redirect()->route('admin.guardians.index')->with('success', 'Parent/guardian deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Guardian $guardian = null): array
    {
        $userId = $guardian?->user_id;

        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'notify_pref' => ['required', Rule::in(['push', 'email', 'sms', 'none'])],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$guardian ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }
}
