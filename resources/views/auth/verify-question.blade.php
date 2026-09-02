@extends('layouts.auth-guest')

@section('title', 'Verify Account — NB Poultry Farm')
@section('eyebrow', 'Account recovery · Step 2 of 3')
@section('heading', 'Verify account')
@section('sub', 'Answer your security question to confirm it is you.')

@section('content')
    <div class="field">
        <label class="field-label" for="username">Username</label>
        <div class="input-icon-wrap">
            <span class="input-icon" aria-hidden="true">👤</span>
            <input
                type="text"
                id="username"
                value="{{ $user->username }}"
                readonly
                tabindex="-1"
            >
        </div>
    </div>

    <div class="field">
        <label class="field-label">Security question</label>
        <div class="question-box">{{ $user->security_question }}</div>
    </div>

    <form method="POST" action="{{ route('forgot.verify') }}" data-loading-form novalidate>
        @csrf

        <div class="field {{ $errors->has('answer') ? 'is-invalid' : '' }}">
            <label class="field-label" for="answer">Answer <span class="req" aria-hidden="true">*</span></label>
            <div class="input-icon-wrap">
                <span class="input-icon" aria-hidden="true">🔑</span>
                <input
                    type="text"
                    id="answer"
                    name="answer"
                    value="{{ old('answer') }}"
                    placeholder="Enter your answer"
                    autocomplete="off"
                    autofocus
                    required
                >
            </div>
            <p class="field-error">{{ $errors->first('answer') }}</p>
        </div>

        <button type="submit" class="login-btn" data-loading="Verifying…">Verify</button>
    </form>

    <div class="auth-back">
        <a href="{{ route('forgot.password') }}" class="forgot-password">Use a different username</a>
    </div>
@endsection
