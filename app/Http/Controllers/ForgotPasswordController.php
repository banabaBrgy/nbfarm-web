<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Recaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    public function showForm()
    {
        return view('auth.forgot-password');
    }

    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => 'required',
        ]);

        $username = trim((string) $request->username);
        $user = User::where('username', $username)->first();

        $request->session()->forget([
            'reset_verified',
            'reset_user_id',
            'reset_username',
            'reset_session_version',
        ]);

        $request->session()->put('reset_username', $username);

        if ($user && $user->hasSecurityAnswer() && ! $this->isInactive($user)) {
            $request->session()->put('reset_user_id', $user->id);
        }

        return redirect()->route('forgot.verify.form');
    }

    public function showVerifyForm()
    {
        $username = (string) session('reset_username');

        if ($username === '') {
            return redirect('/forgot-password')->with('error', 'Session expired.');
        }

        return view('auth.verify-question', [
            'username' => $username,
        ]);
    }

    public function verifyAnswer(Request $request)
    {
        $username = Str::lower(trim((string) session('reset_username')));
        $attemptKey = $this->attemptKey($request, $username);

        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            $seconds = RateLimiter::availableIn($attemptKey);

            return back()->with('error', 'Too many attempts. Please try again in '.$seconds.' seconds.');
        }

        $request->validate([
            'answer' => 'required',
            'g-recaptcha-response' => ['required'],
        ], [
            'g-recaptcha-response.required' => 'Please verify that you are not a robot.',
        ]);

        if (! Recaptcha::passes($request)) {
            throw ValidationException::withMessages([
                'g-recaptcha-response' => 'reCAPTCHA verification failed. Please try again.',
            ]);
        }

        $user = User::find(session('reset_user_id'));
        $matches = $user
            && ! $this->isInactive($user)
            && $user->hasSecurityAnswer()
            && $user->securityAnswerMatches((string) $request->answer);

        if (! $matches) {
            RateLimiter::hit($attemptKey, 900);

            return back()->with('error', 'Incorrect answer.');
        }

        RateLimiter::clear($attemptKey);
        $request->session()->put('reset_verified', true);
        $request->session()->put('reset_session_version', (int) $user->session_version);

        return redirect()->route('forgot.reset.form');
    }

    public function showResetForm()
    {
        $user = $this->verifiedUser();

        if (! $user) {
            return redirect('/forgot-password')->with('error', 'Session expired.');
        }

        return view('auth.reset-password', compact('user'));
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $this->verifiedUser();

        if (! $user) {
            return redirect('/forgot-password')->with('error', 'Session expired.');
        }

        $user->password = $request->password;
        $user->rotateAccess();

        $request->session()->forget([
            'reset_user_id',
            'reset_verified',
            'reset_username',
            'reset_session_version',
        ]);

        return redirect('/login')
            ->with('success', 'Password reset successful.');
    }

    private function verifiedUser(): ?User
    {
        if (! session('reset_verified')) {
            return null;
        }

        $user = User::find(session('reset_user_id'));

        if (
            ! $user
            || $this->isInactive($user)
            || ! $user->hasSecurityAnswer()
            || (int) session('reset_session_version') !== (int) $user->session_version
        ) {
            return null;
        }

        return $user;
    }

    private function isInactive(User $user): bool
    {
        return strtolower((string) $user->status) === 'inactive';
    }

    private function attemptKey(Request $request, string $username): string
    {
        return 'forgot-verify|'.$username.'|'.sha1((string) $request->ip());
    }
}
