<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'NB Poultry Farm Management System')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    @stack('head')
</head>

<body>
    <div class="login-wrapper">
        @include('auth.partials.brand-panel')

        <div class="right-panel">
            <div class="form-eyebrow">@yield('eyebrow', 'Account recovery')</div>
            <div class="title">@yield('heading')</div>
            <div class="subtitle">@yield('sub')</div>

            @if(session('error'))
                <div class="error-box" role="alert">{{ session('error') }}</div>
            @endif

            @if(session('success'))
                <div class="success-box" role="status">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-box" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            @yield('content')

            <div class="footer">NB Poultry Farm &middot; Est. San Jose, Batangas &middot; Registry {{ date('Y') }}</div>
        </div>
    </div>

    <script src="{{ asset('js/auth-recovery.js') }}"></script>
    @stack('scripts')
</body>

</html>
