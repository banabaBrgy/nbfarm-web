<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    /**
     * Show Login Page
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle Login
     */
    public function login(Request $request)
    {
        // Validate Input
        $request->validate([
            'username' => 'required',
            'password' => 'required',
            'terms' => 'accepted',
            'g-recaptcha-response' => 'required',
        ], [
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
            'terms.accepted' => 'You must agree to the Terms and Conditions before logging in.',
            'g-recaptcha-response.required' => 'Please verify that you are not a robot.',
        ]);

        // Verify Google reCAPTCHA
        $recaptchaResponse = Http::asForm()->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip(),
            ]
        );

        $recaptchaBody = $recaptchaResponse->json();

        if (!isset($recaptchaBody['success']) || !$recaptchaBody['success']) {
            return back()
                ->withErrors([
                    'g-recaptcha-response' => 'reCAPTCHA verification failed. Please try again.',
                ])
                ->withInput();
        }

        // Login Credentials
        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];

        // Attempt Login
       // Attempt Login
if (Auth::attempt($credentials, $request->has('remember'))) {

    $request->session()->regenerate();

    $user = Auth::user();

    // Normalize role
    $role = strtolower(trim($user->role));

    switch ($role) {

        case 'farm owner':
        case 'owner':
            return redirect()->route('owner.dashboard');

        case 'farm manager':
        case 'manager':
            return redirect()->route('manager.dashboard');

        case 'poultry staff':
        case 'staff':
            return redirect()->route('staff.dashboard');

        default:
            Auth::logout();

            return redirect()
                ->route('login')
                ->withErrors([
                    'username' => 'Unauthorized role.',
                ]);
    }
}

// ✅ Idinagdag: kailangan mag-return dito kung fumail ang Auth::attempt
return back()
    ->withErrors([
        'username' => 'Invalid username or password.',
    ])
    ->withInput();
    }


    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}