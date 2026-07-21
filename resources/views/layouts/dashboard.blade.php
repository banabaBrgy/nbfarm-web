@extends('layouts.sidebar')

@section('title', 'Dashboard')

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>NB Poultry Farm - @yield('title')</title>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

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

                <button type="submit">

                    <i class="fas fa-sign-out-alt"></i>

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