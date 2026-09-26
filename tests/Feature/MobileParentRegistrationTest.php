<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileParentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['first_name' => 'Maria', 'last_name' => 'Cruz', 'username' => 'maria-parent',
            'password' => 'Securepass123!', 'password_confirmation' => 'Securepass123!'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        app('url')->forceRootUrl('http://localhost');
        Role::firstOrCreate(['name' => 'parent']);
    }

    public function test_mobile_registration_creates_parent_and_guardian_and_allows_login(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $this->postJson('/api/v1/auth/register/parent', [...$this->payload(), 'role_id' => $admin->id])
            ->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.username', 'maria-parent');
        $user = User::where('username', 'maria-parent')->firstOrFail();
        $this->assertTrue($user->hasRole('parent'));
        $this->assertTrue(Hash::check('Securepass123!', $user->password));
        $this->assertDatabaseHas('guardians', ['user_id' => $user->id, 'first_name' => 'Maria', 'notify_pref' => 'push']);
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'Securepass123!', 'device_name' => 'test'])
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_invalid_confirmation_and_duplicate_username_do_not_create_accounts(): void
    {
        $this->postJson('/api/v1/auth/register/parent', [...$this->payload(), 'password_confirmation' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
        $this->postJson('/api/v1/auth/register/parent', $this->payload())->assertCreated();
        $this->postJson('/api/v1/auth/register/parent', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('guardians', 1);
    }
}
