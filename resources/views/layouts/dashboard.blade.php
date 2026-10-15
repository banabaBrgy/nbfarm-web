@extends('layouts.sidebar')

@section('title', 'Dashboard')

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>NB Poultry Farm - @yield('title')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
    <meta name="theme-color" content="#216E4B">


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite([
        'resources/css/app.css',
        'resources/css/dashboard.css',
        'resources/js/app.js'
    ])
    @stack('styles')
</head>

<body>

    <div class="wrapper">

        <!-- SIDEBAR -->

        <aside class="sidebar">

            <div class="profile">

                <div class="profile-image">

                    <i class="fas fa-user"></i>

                </div>

                <div class="role">

                    POULTRY STAFF

                </div>

            </div>

            <nav>

                <ul>

                    <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">

                        <a href="{{ route(auth()->user()->role . '.dashboard') }}"></a>

                            <i class="fas fa-chart-line"></i>

                            Dashboard

                        </a>

                    </li>

                    <li class="{{ request()->routeIs('production') ? 'active' : '' }}">

                        <a href="{{ route(auth()->user()->role . '.production') }}">

                            <i class="fas fa-egg"></i>

                            Production

                        </a>

                    </li>

                    <li class="{{ request()->routeIs('inventory') ? 'active' : '' }}">

                        <a href="{{ route(auth()->user()->role . '.inventory') }}">

                            <i class="fas fa-box"></i>

                            Inventory

                        </a>

                    </li>

                </ul>

            </nav>

            <form action="{{ route('logout') }}" method="POST" class="logout">

                @csrf

                <button type="submit" aria-label="Logout" title="Logout">

                    Logout

                </button>

            </form>

        </aside>

        <!-- MAIN -->

        <main class="main-content">


            <!-- PAGE -->

            @yield('content')

        </main>

    </div>

    @stack('scripts')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>

</html>