<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetCodeService
{
    /** @return 'sent'|'not_found'|'no_email' */
    public function sendCode(string $identifier): string
    {
        $user = $this->findUser($identifier);

        if (! $user) {
            return 'not_found';
        }

        if (! $user->email) {
            return 'no_email';
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        $user->notify(new PasswordResetCodeNotification($code));

        return 'sent';
    }

    public function resetPassword(string $identifier, string $code, string $password): bool
    {
        return DB::transaction(function () use ($identifier, $code, $password): bool {
            $user = $this->findUser($identifier);

            if (! $user || ! $user->email) {
                return false;
            }

            $reset = DB::table('password_reset_tokens')->where('email', $user->email)->first();

            if (! $reset || ! $reset->created_at || now()->subMinutes(10)->gt($reset->created_at) || ! Hash::check($code, $reset->token)) {
                return false;
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return true;
        });
    }

    private function findUser(string $identifier): ?User
    {
        return User::query()
            ->where('username', $identifier)
            ->orWhere('email', strtolower($identifier))
            ->first();
    }
}