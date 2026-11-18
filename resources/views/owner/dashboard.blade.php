
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
        $eggsChange = $eggsChange ?? 0;
        $feedStock = $feedStock ?? 0;
        $feedMin = $feedMin ?? 0;
        $feedLow = $feedLow ?? false;
        $dispatchedTrays = $dispatchedTrays ?? 0;
        $pendingDeliveries = $pendingDeliveries ?? 0;
        $revenueToday = $revenueToday ?? 0;
        $eggsTotal = $eggsTotal ?? 0;
        $eggTrays = $eggTrays ?? 0;
        $vitamins = $vitamins ?? 0;
        $vitaminMin = $vitaminMin ?? 0;
        $vitaminLow = $vitaminLow ?? false;
        $lowStockAlert = $lowStockAlert ?? null;
        $predictedEggs = $predictedEggs ?? 0;
    @endphp

    <!-- Header -->
    <div class="dashboard-header">
        <div>
            <h2>Dashboard</h2>
            <p>Welcome back! Here's today's poultry farm overview.</p>
        </div>
        <div class="dashboard-header-tools">
            <x-weather-widget />
        </div>
    </div>

    @include('dashboard.low-stock-alert')

    @include('dashboard.role-todo')

    @include('dashboard.farm-kpis')

    <!-- Stat cards -->
    <div class="stats-grid stats-grid--four">

        {{-- Eggs --}}
        <div class="stat-card stat-card--eggs">
            <div class="stat-icon">
                <i class="fas fa-egg"></i>
            </div>

            <div class="stat-info">
                <span class="stat-title">Total Eggs Today</span>

                <h2>{{ number_format($eggsToday) }}</h2>

                <small class="{{ $eggsChange >= 0 ? 'stat-trend-up' : 'stat-trend-down' }}">
                    {{ $eggsChange >= 0 ? '+' : '-' }}{{ abs($eggsChange) }}% vs yesterday
                </small>
            </div>
        </div>

        {{-- Predicted eggs (Python linear regression) --}}
        <div
            class="stat-card stat-card--predicted stat-card--clickable"
            role="button"
            tabindex="0"
            data-bs-toggle="modal"
            data-bs-target="#forecastSummaryModal"
            aria-label="View forecast summary by size">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Predicted Eggs</span>
                <h2>{{ number_format($predictedEggs) }}</h2>
                <small>Next-day forecast · tap for sizes</small>
            </div>
        </div>

        @if($role != 'staff')

            {{-- Dispatch --}}
            <div class="stat-card stat-card--dispatch">
                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>

                <div class="stat-info">
                    <span class="stat-title">Egg Trays Dispatched</span>

                    <h2>{{ number_format($dispatchedTrays) }}</h2>

                    <small>{{ $pendingDeliveries }} Pending</small>
                </div>
            </div>

            {{-- Revenue --}}
            <div class="stat-card stat-card--revenue">
                <div class="stat-icon">
                    <i class="fas fa-peso-sign"></i>
                </div>

                <div class="stat-info">
                    <span class="stat-title">Revenue Today</span>

                    <h2>₱{{ number_format($revenueToday, 0) }}</h2>

                    <small>Today's Income</small>
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
