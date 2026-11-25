@extends('layouts.sidebar')

@section('content')
@section('title', 'Dashboard')
@section('page-label', 'Dashboard')

    @push('styles')
        <link rel="stylesheet" href="{{ versioned_asset('css/dashboard.css') }}">
    @endpush
    @php

        $role = auth()->user()->role;
        $eggsToday = $eggsToday ?? 0;
        $feedStock = $feedStock ?? 0;
        $feedMin = $feedMin ?? 0;
        $feedLow = $feedLow ?? false;
        $dispatchedTrays = $dispatchedTrays ?? 0;
        $revenueToday = $revenueToday ?? 0;
        $eggsTotal = $eggsTotal ?? 0;
        $eggTrays = $eggTrays ?? 0;
        $vitamins = $vitamins ?? 0;
        $vitaminMin = $vitaminMin ?? 0;
        $vitaminLow = $vitaminLow ?? false;
        $lowStockAlert = $lowStockAlert ?? null;
        $overviewSubtitle = $overviewSubtitle ?? "Welcome back! Here's today's poultry farm overview.";
        $inventoryRatings = $inventoryRatings ?? [];
        $eggsTitle = 'Total Eggs Produced';
        $dispatchTitle = 'Total Dispatch';
        $revenueTitle = 'Revenue';
        $kpis = $kpis ?? [];
        $productionKpi = $kpis['production'] ?? null;
        $stockKpi = $kpis['stock'] ?? null;
        $dispatchKpi = $kpis['dispatch'] ?? null;
        $revenueKpi = $kpis['revenue'] ?? null;
        // Card totals stay as DashboardOverview DB values; KPI only drives the +/- rating.

    @endphp

    <!-- Header -->
    <div class="dashboard-header">
        <div>
            <h2>Dashboard</h2>
            <p>{{ $overviewSubtitle }}</p>
        </div>
        <div class="dashboard-header-tools">
            <div class="dashboard-header-tools__main">
                @include('dashboard.date-filter')
                <x-weather-widget />
            </div>
        </div>
    </div>

    @include('dashboard.low-stock-alert')

    @include('dashboard.role-todo')

    <!-- Stat cards -->
    <div class="stats-grid stats-grid--four">

        {{-- Total eggs produced --}}
        <div class="stat-card stat-card--eggs">
            <div class="stat-icon">
                <i class="fas fa-egg"></i>
            </div>

            <div class="stat-info">
                <span class="stat-title">{{ $eggsTitle }}</span>

                <h2>{{ number_format($eggsToday) }}</h2>

                @include('dashboard.target-rating', ['rating' => $productionKpi])
            </div>
        </div>

        {{-- Eggs stock on hand --}}
        <div class="stat-card stat-card--predicted">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Eggs Stock</span>
                <h2>{{ number_format($eggsTotal) }}</h2>

                @include('dashboard.target-rating', ['rating' => $stockKpi])
            </div>
        </div>

        @if($role != 'staff')

            {{-- Dispatch --}}
            <div class="stat-card stat-card--dispatch">
                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>

                <div class="stat-info">
                    <span class="stat-title">{{ $dispatchTitle }}</span>

                    <h2>{{ number_format($dispatchedTrays) }}</h2>

                    @include('dashboard.target-rating', ['rating' => $dispatchKpi])
                </div>
            </div>

            {{-- Revenue --}}
            <div class="stat-card stat-card--revenue">
                <div class="stat-icon">
                    <i class="fas fa-peso-sign"></i>
                </div>

                <div class="stat-info">
                    <span class="stat-title">{{ $revenueTitle }}</span>

                    <h2>₱{{ number_format($revenueToday, 0) }}</h2>

                    @include('dashboard.target-rating', ['rating' => $revenueKpi])
                </div>
            </div>

        @else

            {{-- Staff --}}
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>

                <div class="stat-info">
                    <span class="stat-title">Egg Trays</span>

                    <h2>{{ number_format($eggTrays) }}</h2>

                    <small>Available</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-capsules"></i>
                </div>

                <div class="stat-info">
                    <span class="stat-title">Vitamins</span>

                    <h2>{{ number_format($vitamins) }}</h2>

                    <small>{{ $vitaminLow ? 'Low Stock' : 'Available' }}</small>
                </div>
            </div>

        @endif

    </div>

    <!-- Chart + Inventory side by side -->
    <div class="content-grid">
        @include('dashboard.egg-production-chart-card', [
            'trendAction' => route('owner.dashboard'),
        ])

        @include('dashboard.inventory-levels-panel')
    </div>

    <x-forecast-summary-modal
        :forecast-summary="$forecastSummary ?? null"
        :previous-forecast-summary="$previousForecastSummary ?? null" />

@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" integrity="sha384-DhxhYObIMeMNGyAG7iK11OHzBIKyEIeRL0ad1iFPAOwZB8iirUlTT0O/WJJUk8+o" crossorigin="anonymous"></script>
    @include('dashboard.production-trend-script')
@endpush
