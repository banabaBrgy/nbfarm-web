@extends('layouts.sidebar')

@section('title', 'Dashboard')

@section('content')

    @php
        $eggsChange = $eggsChange ?? 0;
        $feedStock = $feedStock ?? 0;
        $feedMin = $feedMin ?? 0;
        $feedLow = $feedLow ?? false;
        $eggsTotal = $eggsTotal ?? 0;
        $eggTrays = $eggTrays ?? 0;
        $vitamins = $vitamins ?? 0;
        $vitaminMin = $vitaminMin ?? 0;
        $vitaminLow = $vitaminLow ?? false;
        $productionTrend = $productionTrend ?? ['months' => [], 'years' => [], 'selectedYear' => null, 'series' => []];
    @endphp

    <div class="dashboard-header">
        <div>
            <h2>Staff Dashboard</h2>
            <p>Welcome back! Here's today's poultry farm overview.</p>
        </div>
        <x-weather-widget />
    </div>

    @include('dashboard.low-stock-alert')

    <div class="stats-grid stats-grid--five">
        <div class="stat-card stat-card--eggs">
            <div class="stat-icon">
                <i class="fas fa-egg"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Total Eggs Today</span>
                <h2>{{ number_format($eggsToday) }}</h2>
                <small class="{{ $eggsChange >= 0 ? 'stat-trend-up' : 'stat-trend-down' }}">{{ $eggsChange >= 0 ? '+' : '-' }}{{ abs($eggsChange) }}% vs yesterday</small>
            </div>
        </div>

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
                <h2>{{ number_format($predictedEggs ?? 0) }}</h2>
                <small>Next-day forecast · tap for sizes</small>
            </div>
        </div>

        <div class="stat-card stat-card--inventory">
            <div class="stat-icon">
                <i class="fas fa-dove"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Active Batches</span>
                <h2>{{ $activeBatches }}</h2>
                <small>Currently producing</small>
            </div>
        </div>

        <div class="stat-card stat-card--feed">
            <div class="stat-icon">
                <i class="fas fa-wheat-awn"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Feeds Available</span>
                <h2>{{ number_format($feedStock) }}</h2>
                <small>{{ $feedLow ? 'Low Stock' : 'Bags Remaining' }}</small>
            </div>
        </div>

        <div class="stat-card stat-card--inventory">
            <div class="stat-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Egg Trays</span>
                <h2>{{ number_format($eggTrays) }}</h2>
                <small>Available</small>
            </div>
        </div>
    </div>

    <div class="content-grid">
        @include('dashboard.egg-production-chart-card', [
            'productionTrend' => $productionTrend,
            'trendAction' => route('staff.dashboard'),
        ])

        @include('dashboard.inventory-levels-panel')
    </div>

    <x-forecast-summary-modal :forecast-summary="$forecastSummary ?? null" />

@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    @include('dashboard.production-trend-script')
@endpush
