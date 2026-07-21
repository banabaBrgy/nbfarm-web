<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    // Step 1
    public function showForm()
    {
        return view('auth.forgot-password');
    }

    // Step 2
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

        return view('auth.verify-question', compact('user'));
    }

   // Step 3
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

    return view('auth.reset-password', compact('user'));
}

    // Step 4
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed'
        ]);

        $user = User::find(session('reset_user_id'));

        if (!$user) {
            return redirect('/forgot-password');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        session()->forget('reset_user_id');

        return redirect('/login')
            ->with('success', 'Password reset successful.');
    }
}