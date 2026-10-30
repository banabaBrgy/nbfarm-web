@extends('layouts.auth-guest')

@push('head')
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endpush

@section('title', 'Verify Account — NB Poultry Farm')
@section('eyebrow', 'Account recovery · Step 2 of 3')
@section('heading', 'Verify account')
@section('sub', 'Enter the answer to the security question on this account.')

@section('content')
    <div class="field">
        <label class="field-label" for="username">Username</label>
        <div class="input-icon-wrap">
            <span class="input-icon" aria-hidden="true">👤</span>
            <input
                type="text"
                id="username"
                value="{{ $username }}"
                readonly
                tabindex="-1"
            >
        </div>
    </div>

    <form method="POST" action="{{ route('forgot.verify') }}" data-loading-form novalidate>
        @csrf

        <div class="field {{ $errors->has('answer') ? 'is-invalid' : '' }}">
            <label class="field-label" for="answer">Security answer <span class="req" aria-hidden="true">*</span></label>
            <div class="input-icon-wrap">
                <span class="input-icon" aria-hidden="true">🔑</span>
                <input
                    type="password"
                    id="answer"
                    name="answer"
                    placeholder="Enter your answer"
                    autocomplete="off"
                    autofocus
                    required
                >
            </div>
            <p class="field-error">{{ $errors->first('answer') }}</p>
        </div>

        <div class="field recaptcha-field {{ $errors->has('g-recaptcha-response') ? 'is-invalid' : '' }}" data-field="recaptcha">
            <div class="recaptcha-frame">
                <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
            </div>
            <p class="field-error" id="recaptcha-error" role="alert">{{ $errors->first('g-recaptcha-response') }}</p>
        </div>

        <button type="submit" class="login-btn" data-loading="Verifying…">Verify</button>
    </form>

    <div class="auth-back">
        <a href="{{ route('forgot.password') }}" class="forgot-password">Use a different username</a>
    </div>
@endsection
