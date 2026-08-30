<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show Login Page
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectAuthenticatedUser(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Handle Login
     */
    public function login(Request $request)
    {
        if (Auth::check()) {
            return $this->redirectAuthenticatedUser(Auth::user());
        }

        $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'terms' => ['accepted'],
            'g-recaptcha-response' => ['required'],
        ], [
            'username.required' => 'Username is required.',
            'username.min' => 'Username must be at least 3 characters.',
            'username.max' => 'Username may not be greater than 50 characters.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.max' => 'Password is too long.',
            'terms.accepted' => 'You must agree to the Terms and Conditions before logging in.',
            'g-recaptcha-response.required' => 'Please verify that you are not a robot.',
        ]);

        $this->ensureIsNotRateLimited($request);

        $recaptchaResponse = Http::asForm()->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]
        );

        $recaptchaBody = $recaptchaResponse->json();

        if (! isset($recaptchaBody['success']) || ! $recaptchaBody['success']) {
            throw ValidationException::withMessages([
                'g-recaptcha-response' => 'reCAPTCHA verification failed. Please try again.',
            ]);
        }

        $credentials = [
            'username' => trim($request->username),
            'password' => $request->password,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'login' => 'Invalid username or password.',
            ]);
        }

        $user = Auth::user();

        if (strtolower((string) $user->status) === 'inactive') {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Your account is inactive. Please contact the farm owner.',
            ]);
        }

        $role = strtolower(trim((string) $user->role));
        $destination = $this->dashboardRouteForRole($role);

        if (! $destination) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Unauthorized role.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route($destination);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'You have been signed out.');
    }

    private function redirectAuthenticatedUser($user)
    {
        $destination = $this->dashboardRouteForRole(strtolower(trim((string) $user->role)));

        if (! $destination) {
            Auth::logout();

            return redirect()
                ->route('login')
                ->withErrors(['login' => 'Unauthorized role.']);
        }

        return redirect()->route($destination);
    }

    private function dashboardRouteForRole(string $role): ?string
    {
        return match ($role) {
            'farm owner', 'owner' => 'owner.dashboard',
            'farm manager', 'manager' => 'manager.dashboard',
            'poultry staff', 'staff' => 'staff.dashboard',
            default => null,
        };
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => 'Too many login attempts. Please try again in '.$seconds.' seconds.',
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower(trim((string) $request->input('username'))).'|'.$request->ip());
    }
}
