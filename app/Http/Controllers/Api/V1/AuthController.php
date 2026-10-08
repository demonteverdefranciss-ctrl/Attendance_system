<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('role')->where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('Invalid credentials.', 'UNAUTHENTICATED', 401);
        }

        if (! $user->is_active) {
            return $this->fail('This account has been deactivated.', 'FORBIDDEN', 403);
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->ok(['token' => $token, 'user' => $this->payload($user)]);
    }

    public function sendPasswordResetCode(Request $request, PasswordResetCodeService $passwordReset): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $result = $passwordReset->sendCode($data['identifier']);
        $message = match ($result) {
            'sent' => 'We found your account and sent a reset code to its registered email address.',
            'no_email' => 'We found your account, but no email address is registered. Please contact the school administrator.',
            default => 'No account matches that username or email. Check your entry and try again.',
        };

        return $this->ok([
            'status' => $result,
            'message' => $message,
        ]);
    }

    public function resetPassword(Request $request, PasswordResetCodeService $passwordReset): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $passwordReset->resetPassword($data['identifier'], $data['code'], $data['password'])) {
            return $this->fail('The code is invalid or expired. Request a new code and try again.', 'INVALID_RESET_CODE', 422);
        }

        return $this->ok(['message' => 'Your password has been changed. You can now sign in.']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->payload($request->user()->load('role')));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(['message' => 'Logged out.']);
    }

    /**
     * Register / update the guardian's FCM device token for push notifications.
     */
    public function deviceToken(Request $request): JsonResponse
    {
        $data = $request->validate(['fcm_token' => ['required', 'string', 'max:512']]);

        $guardian = $request->user()->guardian;

        if (! $guardian) {
            return $this->fail('Only guardian accounts can register device tokens.', 'FORBIDDEN', 403);
        }

        $guardian->update(['fcm_token' => $data['fcm_token']]);

        return $this->ok(['message' => 'Device token registered.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role?->name,
        ];
    }
}
