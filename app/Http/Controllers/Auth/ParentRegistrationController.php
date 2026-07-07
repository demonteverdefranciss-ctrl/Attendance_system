<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ParentRegistrationController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/RegisterParent');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($data, $request) {
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

            $this->audit->log(
                action: 'parent_registered',
                userId: $user->id,
                entity: $guardian,
                newValues: [
                    'username' => $user->username,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('parent.dashboard')
            ->with('success', 'Parent account created. You can now link your child using their LRN.');
    }
}
