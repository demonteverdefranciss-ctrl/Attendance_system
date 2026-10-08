<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_user_can_reset_password_with_code_sent_to_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['username' => 'parent-one']);

        $this->post(route('password.email'), ['identifier' => $user->username])
            ->assertRedirect()
            ->assertSessionHas('status', 'We found your account and sent a reset code to its registered email address.')
            ->assertSessionHas('code_sent', true);

        $code = null;
        Notification::assertSentTo($user, PasswordResetCodeNotification::class, function ($notification) use (&$code) {
            $code = (new \ReflectionProperty($notification, 'code'))->getValue($notification);

            return true;
        });

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertTrue(Hash::check($code, \DB::table('password_reset_tokens')->where('email', $user->email)->value('token')));

        $this->post(route('password.update'), [
            'identifier' => $user->email,
            'code' => $code,
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewSecurePass123!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_mobile_reset_endpoints_accept_username_and_reject_invalid_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['username' => 'mobile-parent']);

        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => $user->username])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'sent');

        $code = null;
        Notification::assertSentTo($user, PasswordResetCodeNotification::class, function ($notification) use (&$code) {
            $code = (new \ReflectionProperty($notification, 'code'))->getValue($notification);

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'identifier' => $user->username,
            'code' => '000000',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/reset-password', [
            'identifier' => $user->username,
            'code' => $code,
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('NewSecurePass123!', $user->fresh()->password));
    }

    public function test_unknown_account_gets_a_not_found_response(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'unknown-account'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'not_found')
            ->assertJsonPath('data.message', 'No account matches that username or email. Check your entry and try again.');

        Notification::assertNothingSent();
    }

    public function test_matching_account_without_email_is_reported_without_sending_a_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['username' => 'no-email-user', 'email' => null]);

        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => $user->username])
            ->assertOk()
            ->assertJsonPath('data.status', 'no_email')
            ->assertJsonPath('data.message', 'We found your account, but no email address is registered. Please contact the school administrator.');

        Notification::assertNothingSent();
    }
}