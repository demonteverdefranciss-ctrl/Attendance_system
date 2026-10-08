<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'statusType' => session('status_type'),
            'identifier' => old('identifier', ''),
            'codeSent' => session('code_sent', false),
        ]);
    }

    public function sendCode(Request $request, PasswordResetCodeService $passwordReset): RedirectResponse
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

        return back()->withInput()
            ->with('status', $message)
            ->with('status_type', $result === 'sent' ? 'success' : 'warning')
            ->with('code_sent', $result === 'sent');
    }

    public function reset(Request $request, PasswordResetCodeService $passwordReset): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $passwordReset->resetPassword($data['identifier'], $data['code'], $data['password'])) {
            return back()->withInput()->with('status', 'The code could not be verified. Request a new code if it has expired.')
                ->with('status_type', 'warning')
                ->with('code_sent', true)
                ->withErrors(['code' => 'The code is invalid or expired. Request a new code and try again.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been changed. You can now sign in.');
    }
}