<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>NB Poultry Farm - @yield('title')</title>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    @vite([
        'resources/css/app.css',
        'resources/css/sidebar.css',
        'resources/css/owner.dashboard.css',
        'resources/css/production.css',
        'resources/css/users.css',
        'resources/js/app.js'
    ])
    @stack('styles')

</head>

<body>

    @php
        $role = strtolower(trim(auth()->user()->role));

        if ($role === 'poultry staff') {
            $role = 'staff';
        }

        if ($role === 'farm manager') {
            $role = 'manager';
        }

        if ($role === 'farm owner') {
            $role = 'owner';

        }

        $roleLabels = [
            'owner' => 'FARM OWNER',
            'manager' => 'FARM MANAGER',
            'staff' => 'POULTRY STAFF',
        ];

        $menuItems = [

            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-chart-line',
                'route' =>
                    $role == 'owner'
                    ? 'owner.dashboard'
                    : ($role == 'manager'
                        ? 'manager.dashboard'
                        : 'staff.dashboard'),
                'roles' => ['owner', 'manager', 'staff'],
            ],
            [
                'label' => 'Production',
                'icon' => 'fas fa-egg',
                'route' =>
                    $role == 'owner'
                    ? 'owner.production'
                    : ($role == 'manager'
                        ? 'manager.production'
                        : 'staff.production'),
                'roles' => ['owner', 'manager', 'staff'],
            ],
            [
                'label' => 'Inventory',
                'icon' => 'fas fa-box',
                'route' =>
                    $role == 'owner'
                    ? 'owner.inventory'
                    : ($role == 'manager'
                        ? 'manager.inventory'
                        : 'staff.inventory'),
                'roles' => ['owner', 'manager', 'staff'],
            ],
            [
                'label' => 'Dispatch',
                'icon' => 'fas fa-truck',
                'route' =>
                    $role == 'owner'
                    ? 'owner.dispatch'
                    : ($role == 'manager'
                        ? 'manager.dispatch'
                        : 'dispatch'),
                'roles' => ['owner', 'manager'],
            ],
            [
                'label' => 'Reports',
                'icon' => 'fas fa-file-alt',
                'route' => 'manager.reports',
                'roles' => ['manager'],
            ],
            [
                'label' => 'Sales',
                'icon' => 'fas fa-money-bill-wave',
                'route' => 'owner.revenue',
                'roles' => ['owner'],
            ],
            [
                'label' => 'User Management',
                'icon' => 'fas fa-users',
                'route' => 'owner.users',
                'roles' => ['owner'],
            ],
        ];

    @endphp

    <div class="wrapper">

        <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="profile">
            <div class="profile-image">
                <i class="fas fa-user"></i>
            </div>
            <div class="role">
                {{ $roleLabels[$role] ?? strtoupper($role) }}
            </div>
        </div>

        <nav>
            <ul>
                @foreach ($menuItems as $item)
                    @if (in_array($role, $item['roles']) && Route::has($item['route']))
                        <li class="{{ request()->routeIs($item['route']) ? 'active' : '' }}">
                            <a href="{{ route($item['route']) }}">
                                <i class="{{ $item['icon'] }}"></i>
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <form id="logoutForm" action="{{ route('logout') }}" method="POST" class="logout">
    @csrf
    <button type="submit">
        <i class="fas fa-sign-out-alt"></i>
        Logout
    </button>
</form>

    </aside>

        <!-- MAIN -->
        <main class="main-content">


            @yield('content')

        </main>

    </div>

    <footer class="footer">
        <span>NB POULTRY FARM </span>
        <span class="sep"> | </span>
        <span>Farm Management System</span>
    </footer>

    @stack('scripts')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('logoutForm').addEventListener('submit', function(e) {
    e.preventDefault();

    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of your account.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2E7D32',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Log out',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});
</script>

</body>

</html>