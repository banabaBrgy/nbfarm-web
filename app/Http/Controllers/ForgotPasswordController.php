<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ForgotPasswordController extends Controller
{
    // Step 1
    public function showForm()
    {
        return view('auth.forgot-password');
    }

    // Step 2 - process username
    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => 'required'
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            return back()->with('error', 'Username not found.');
        }

        session([
            'reset_user_id' => $user->id
        ]);

        return redirect()->route('forgot.verify.form');
    }

    // Step 2b - show security question (GET)
    public function showVerifyForm()
    {
        $user = User::find(session('reset_user_id'));

        if (!$user) {
            return redirect('/forgot-password')->with('error', 'Session expired.');
        }

        return view('auth.verify-question', compact('user'));
    }

    // Step 3 - process security answer
    public function verifyAnswer(Request $request)
    {
        $request->validate([
            'answer' => 'required'
        ]);

        $user = User::find(session('reset_user_id'));

        if (!$user) {
            return redirect('/forgot-password')
                ->with('error', 'Session expired.');
        }

        if (strtolower(trim($request->answer)) != strtolower(trim($user->security_answer))) {
            return back()->with('error', 'Incorrect answer.');
        }

        session(['reset_verified' => true]);

        return redirect()->route('forgot.reset.form');
    }

    // Step 3b - show reset password form (GET)
    public function showResetForm()
    {
        $user = User::find(session('reset_user_id'));

        if (!$user || !session('reset_verified')) {
            return redirect('/forgot-password')->with('error', 'Session expired.');
        }

        return view('auth.reset-password', compact('user'));
    }

    // Step 4 - process new password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = User::find(session('reset_user_id'));

        if (!$user) {
            return redirect('/forgot-password');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        session()->forget(['reset_user_id', 'reset_verified']);

        return redirect('/login')
            ->with('success', 'Password reset successful.');
    }
}
