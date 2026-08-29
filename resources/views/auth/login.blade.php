<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NB Poultry Farm Management System</title>

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/login.css') }}">

</head>

<body>
    <div class="login-wrapper">

        <div class="left-panel">
            <div class="panel-inner">
                <div class="stamp-badge"><span>N·B</span></div>
                <h1>NB Poultry Farm</h1>
                <p class="lede">
                    One registry for production, inventory, dispatch,
                    and forecasting &mdash; kept as tidy as a well-run coop.
                </p>

                <div class="module-list">
                    <div class="module-row">
                        <div class="module-icon">🥚</div>
                        <div>
                            <strong>Production Monitoring</strong>
                            <small>Daily egg counts by batch</small>
                        </div>
                    </div>
                    <div class="module-row">
                        <div class="module-icon">📦</div>
                        <div>
                            <strong>Inventory Management</strong>
                            <small>Feeds, supplements, trays on hand</small>
                        </div>
                    </div>
                    <div class="module-row">
                        <div class="module-icon">📱</div>
                        <div>
                            <strong>QR Egg Tracking</strong>
                            <small>Scan trays in and out</small>
                        </div>
                    </div>
                    <div class="module-row">
                        <div class="module-icon">📈</div>
                        <div>
                            <strong>Forecasting</strong>
                            <small>Where production is heading next</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ledger-tag">Registry &middot; San Jose, Batangas</div>
        </div>

        <div class="right-panel">

            <div class="form-eyebrow">Farm Registry Access</div>
            <div class="title">Welcome back</div>
            <div class="subtitle">Sign in to continue</div>

            @if($errors->any())
                <div class="error-box">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">

                @csrf

                <label class="field-label">Username</label>
                <div class="input-icon-wrap">
                    <span class="input-icon">👤</span>
                    <input type="text" name="username" value="{{ old('username') }}" placeholder="Enter your username" required>
                </div>

                <label class="field-label">Password</label>
                <div class="password-container input-icon-wrap">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <span class="toggle-password" onclick="togglePassword()">👁️</span>
                </div>

                <div class="terms">
                    <input type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : '' }}>
                    <span>
                        I have read and agree to the
                        <a href="{{ route('terms') }}" target="_blank">Terms and Conditions</a>
                    </span>
                </div>

                <div class="remember-row">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <span>Keep me logged in</span>
                    </label>
                    <a href="{{ route('forgot.password') }}" class="forgot-password">Forgot Password?</a>
                </div>

                <div class="recaptcha-frame">
                    <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                </div>

                @error('g-recaptcha-response')
                    <div style="margin-top:-14px; margin-bottom:18px; color:#A63D2F; font-size:13.5px; font-weight:500;">
                        {{ $message }}
                    </div>
                @enderror

                <button type="submit" class="login-btn">Log In</button>

            </form>

            <div class="footer">NB Poultry Farm &middot; Est. San Jose, Batangas &middot; Registry {{ date('Y') }}</div>

        </div>

    </div>

    <script>
        function togglePassword() {
            let password = document.getElementById('password');
            password.type = password.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>

</html>