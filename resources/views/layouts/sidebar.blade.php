<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>NB Poultry Farm - @yield('title')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
    <meta name="theme-color" content="#216E4B">


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha384-nRgPTkuX86pH8yjPJUAFuASXQSSl2/bBUiNV47vSYpKFxHJhbcrGnmlYpYJMeD7a" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    

    @vite([
    'resources/css/app.css',
    'resources/css/sidebar.css',
    'resources/css/owner.dashboard.css',
    'resources/css/weather.css',
    'resources/css/production.css',
    'resources/css/inventory.css',
    'resources/css/users.css',
    'resources/css/report.css',
    'resources/css/owner.report.css',
    'resources/css/app-ui.css',
    'resources/js/app.js'
])

<link rel="stylesheet" href="{{ versioned_asset('css/theme.css') }}">

    @stack('styles')

    <link rel="stylesheet" href="{{ versioned_asset('css/app-ui.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/daylight-ui.css') }}">

    @stack('late-styles')


</head>

<body>
    @php
        $role = \App\Models\User::routeRole();
        $isFarmLead = auth()->user()?->isFarmLead() ?? false;
        $alertUnread = $isFarmLead
            ? \App\Models\StockNotification::query()->where('user_id', auth()->id())->unread()->count()
            : 0;
    @endphp
    <!-- MOBILE HEADER (visible only on ≤768px) -->
    <header class="mobile-header">
        <button class="header-menu-btn" id="headerMenuBtn" type="button" aria-controls="app-sidebar" aria-expanded="true" aria-label="Hide navigation">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-title-group">
            <div class="header-title">
                <h1>NB Farm</h1>
                <p class="header-subtitle">Poultry Management</p>
            </div>
        </div>
        @if($isFarmLead)
            <button type="button" class="alert-bell alert-bell-header" data-alert-bell aria-label="Notifications" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="alert-badge" data-alert-badge @if($alertUnread < 1) hidden @endif>{{ $alertUnread > 9 ? '9+' : $alertUnread }}</span>
            </button>
        @endif
    </header>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    @php
        $roleLabels = [
            'owner' => 'FARM OWNER',
            'manager' => 'FARM MANAGER',
            'staff' => 'POULTRY STAFF',
        ];

        $menuItems = [

            [
                'label' => 'Dashboard',
                'icon' => 'fas fa-chart-line',
                'group' => 'Operations',
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
                'group' => 'Operations',
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
                'group' => 'Operations',
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
                'group' => 'Operations',
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
                'group' => 'Management',
                'route' => $role == 'owner'
                    ? 'owner.reports'
                    : 'manager.reports',
                'roles' => ['owner', 'manager'],
            ],
            [
                'label' => 'Sales',
                'icon' => 'fas fa-money-bill-wave',
                'group' => 'Management',
                'route' => $role == 'owner' ? 'owner.revenue' : 'manager.sales.history',
                'roles' => ['owner', 'manager'],
            ],
            [
                'label' => 'User Management',
                'icon' => 'fas fa-users',
                'group' => 'Management',
                'route' => 'owner.users',
                'roles' => ['owner'],
            ],
        ];

        $visibleMenu = array_values(array_filter($menuItems, function ($item) use ($role) {
            return in_array($role, $item['roles']) && Route::has($item['route']);
        }));

    @endphp

    <div class="wrapper">

        <!-- SIDEBAR -->
    <aside class="sidebar" id="app-sidebar">

        <div class="profile">
            <div class="profile-image" aria-hidden="true">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                <span class="profile-status"></span>
            </div>
            <div class="profile-meta">
                <div class="profile-name" title="{{ auth()->user()->name ?? 'User' }}">
                    {{ auth()->user()->name ?? 'User' }}
                </div>
                <div class="role">
                    {{ $roleLabels[$role] ?? strtoupper($role) }}
                </div>
            </div>
            <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close navigation">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <nav>
            @php $currentGroup = null; @endphp
            @foreach ($visibleMenu as $item)
                @if ($currentGroup !== $item['group'])
                    @if ($currentGroup !== null)
                        </ul>
                    @endif
                    <p class="nav-group-label">{{ $item['group'] }}</p>
                    <ul>
                    @php $currentGroup = $item['group']; @endphp
                @endif
                <li class="{{ request()->routeIs($item['route']) ? 'active' : '' }}">
                    <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}">
                        <i class="{{ $item['icon'] }}"></i>
                        <span class="nav-label">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
            @if ($currentGroup !== null)
                </ul>
            @endif
        </nav>

        <form id="logoutForm" action="{{ route('logout') }}" method="POST" class="logout">
            @csrf
            <button type="submit" aria-label="Logout" title="Logout">
                <i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>
                <span>Logout</span>
            </button>
        </form>

    </aside>

        <!-- MAIN -->
        <main class="main-content">


            @yield('content')

        </main>

    </div>

    <footer class="footer">
        <span>NB POULTRY FARM</span>
        <span class="sep" aria-hidden="true">|</span>
        <span>Farm Management System</span>
    </footer>

    @if($isFarmLead)
    <div id="alertPanel" class="alert-panel" hidden
         data-index="{{ route('alerts.index') }}"
         data-read-base="{{ url('/alerts') }}"
         data-push="{{ route('alerts.push') }}"
         data-subscribe="{{ route('alerts.subscribe') }}"
         data-unsubscribe="{{ route('alerts.unsubscribe') }}">
        <div class="alert-panel-head">
            <div class="alert-panel-title">
                <strong>Notifications</strong>
                <span class="alert-panel-sub">Stock and reports</span>
            </div>
            <button type="button" class="alert-mark-all" data-alert-read-all>Mark all as read</button>
        </div>
        <div class="alert-panel-list" data-alert-list>
            <p class="alert-empty">No alerts.</p>
        </div>
    </div>
    @endif

    @stack('scripts')

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.22.2/dist/sweetalert2.all.min.js" integrity="sha384-kR2tS24oEqwHzfBnAN4yxA8ib4vGEOMaANrncAlq8i0lsgvGoOzSPXFbj5dHYUZK" crossorigin="anonymous"></script>
<script>
document.getElementById('logoutForm').addEventListener('submit', function(e) {
    e.preventDefault();

    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of your account.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3B8F47',
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