@extends('layouts.auth-guest')

@section('title', 'Forgot Password — NB Poultry Farm')
@section('eyebrow', 'Account recovery · Step 1 of 3')
@section('heading', 'Forgot password')
@section('sub', 'Enter your username to start resetting your password.')

@section('content')
    <form method="POST" action="{{ route('forgot.check') }}" data-loading-form novalidate>
        @csrf

        <div class="field {{ $errors->has('username') ? 'is-invalid' : '' }}">
            <label class="field-label" for="username">Username <span class="req" aria-hidden="true">*</span></label>
            <div class="input-icon-wrap">
                <span class="input-icon" aria-hidden="true">👤</span>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="{{ old('username') }}"
                    placeholder="Enter your username"
                    autocomplete="username"
                    autocapitalize="none"
                    autocorrect="off"
                    spellcheck="false"
                    maxlength="50"
                    autofocus
                    required
                >
            </div>
            <p class="field-error">{{ $errors->first('username') }}</p>
        </div>

        <button type="submit" class="login-btn" data-loading="Checking…">Continue</button>
    </form>

    <div class="auth-back">
        <a href="{{ route('login') }}" class="forgot-password">Back to login</a>
    </div>
@endsection
