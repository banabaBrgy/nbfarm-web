@extends('layouts.auth-guest')

@section('title', 'Reset Password — NB Poultry Farm')
@section('eyebrow', 'Account recovery · Step 3 of 3')
@section('heading', 'Reset password')
@section('sub')
    Create a new password for <strong>{{ $user->username }}</strong>.
@endsection

@section('content')
    <form method="POST" action="{{ route('forgot.reset') }}" data-loading-form novalidate>
        @csrf

        <div class="field {{ $errors->has('password') ? 'is-invalid' : '' }}">
            <label class="field-label" for="password">New password <span class="req" aria-hidden="true">*</span></label>
            <div class="password-container input-icon-wrap">
                <span class="input-icon" aria-hidden="true">🔒</span>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter new password"
                    autocomplete="new-password"
                    maxlength="255"
                    required
                    aria-describedby="password-error"
                >
                <button type="button" class="toggle-password" data-toggle-password aria-label="Show password" aria-pressed="false">
                    Show
                </button>
            </div>
            <p class="field-error" id="password-error">{{ $errors->first('password') }}</p>
        </div>

        <div class="field {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}">
            <label class="field-label" for="password_confirmation">Confirm password <span class="req" aria-hidden="true">*</span></label>
            <div class="password-container input-icon-wrap">
                <span class="input-icon" aria-hidden="true">🔒</span>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    autocomplete="new-password"
                    maxlength="255"
                    required
                >
                <button type="button" class="toggle-password" data-toggle-password aria-label="Show password" aria-pressed="false">
                    Show
                </button>
            </div>
            <p class="field-error">{{ $errors->first('password_confirmation') }}</p>
        </div>

        <button type="submit" class="login-btn" data-loading="Updating…">Update password</button>
    </form>

    <div class="auth-back">
        <a href="{{ route('login') }}" class="forgot-password">Back to login</a>
    </div>
@endsection
