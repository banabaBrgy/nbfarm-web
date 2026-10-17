<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NB Poultry Farm Management System</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
    <meta name="theme-color" content="#216E4B">

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ versioned_asset('css/login.css') }}">
</head>

<body>
    <div class="login-wrapper">

        @include('auth.partials.brand-panel')

        <div class="right-panel">

            <div class="form-eyebrow">Farm Registry Access</div>
            <div class="title">Welcome back</div>
            <div class="subtitle">Sign in to continue</div>

            @if(session('success'))
                <div class="success-box" role="status">{{ session('success') }}</div>
            @endif

            @if(session('status'))
                <div class="success-box" role="status">{{ session('status') }}</div>
            @endif

            @error('login')
                <div class="error-box" role="alert">{{ $message }}</div>
            @enderror

            <form id="login-form" method="POST" action="{{ route('login.submit') }}" novalidate>

                @csrf

                <div class="field {{ $errors->has('username') ? 'is-invalid' : '' }}" data-field="username">
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
                            aria-required="true"
                            aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}"
                            aria-describedby="username-error"
                        >
                    </div>
                    <p class="field-error" id="username-error" role="alert">{{ $errors->first('username') }}</p>
                </div>

                <div class="field {{ $errors->has('password') ? 'is-invalid' : '' }}" data-field="password">
                    <label class="field-label" for="password">Password <span class="req" aria-hidden="true">*</span></label>
                    <div class="password-container input-icon-wrap">
                        <span class="input-icon" aria-hidden="true">🔒</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            maxlength="255"
                            required
                            aria-required="true"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            aria-describedby="password-error password-caps"
                        >
                        <button type="button" class="toggle-password" id="toggle-password" aria-label="Show password" aria-pressed="false">
                            Show
                        </button>
                    </div>
                    <p class="caps-hint" id="password-caps" hidden>Caps Lock is on</p>
                    <p class="field-error" id="password-error" role="alert">{{ $errors->first('password') }}</p>
                </div>

                <div class="field terms-field {{ $errors->has('terms') ? 'is-invalid' : '' }}" data-field="terms">
                    <label class="terms">
                        <input
                            type="checkbox"
                            id="terms"
                            name="terms"
                            value="1"
                            {{ old('terms') ? 'checked' : '' }}
                            required
                            aria-required="true"
                            aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}"
                            aria-describedby="terms-error"
                        >
                        <span>
                            I have read and agree to the
                            <a href="{{ route('terms') }}" class="terms-link" data-terms-open>Terms and Conditions</a>
                        </span>
                    </label>
                    <p class="terms-hint" id="terms-hint">Click the checkbox to read the terms first.</p>
                    <p class="field-error" id="terms-error" role="alert">{{ $errors->first('terms') }}</p>
                </div>

                <div class="remember-row">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>Keep me logged in</span>
                    </label>
                    <a href="{{ route('forgot.password') }}" class="forgot-password">Forgot Password?</a>
                </div>

                <div class="field recaptcha-field {{ $errors->has('g-recaptcha-response') ? 'is-invalid' : '' }}" data-field="recaptcha">
                    <div class="recaptcha-frame">
                        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}" data-callback="onLoginCaptcha" data-expired-callback="onLoginCaptchaExpired"></div>
                    </div>
                    <p class="field-error" id="recaptcha-error" role="alert">{{ $errors->first('g-recaptcha-response') }}</p>
                </div>

                <button type="submit" class="login-btn" id="login-submit">Log In</button>

            </form>

            <div class="footer">NB Poultry Farm &middot; Est. San Jose, Batangas &middot; Registry {{ date('Y') }}</div>

        </div>

    </div>

    <div class="terms-modal" id="terms-modal" hidden>
        <div class="terms-modal-backdrop" data-terms-close></div>
        <section class="terms-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title">
            <div class="terms-modal-header">
                <div>
                    <p class="terms-modal-kicker">Before you sign in</p>
                    <h2 id="terms-modal-title">Terms and Conditions</h2>
                </div>
                <button type="button" class="terms-modal-close" data-terms-close aria-label="Close Terms and Conditions">&times;</button>
            </div>
            <div class="terms-modal-body" id="terms-modal-body" tabindex="0">
                @include('partials.terms-content')
            </div>
            <div class="terms-modal-footer">
                <p class="terms-read-status" id="terms-read-status" role="status">
                    Scroll to the end to continue.
                </p>
                <button type="button" class="terms-agree-btn" id="terms-agree" disabled>
                    Done — I agree
                </button>
            </div>
        </section>
    </div>

    <script src="{{ versioned_asset('js/login.js') }}"></script>
</body>

</html>
