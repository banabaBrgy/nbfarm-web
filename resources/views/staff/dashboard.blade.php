
@extends('layouts.sidebar')

@section('title', 'Dashboard')

@section('content')

    @push('styles')
        <link rel="stylesheet" href="{{ versioned_asset('css/dashboard.css') }}">
    @endpush

    @php
        $feedStock = $feedStock ?? 0;
        $feedMin = $feedMin ?? 0;
        $feedLow = $feedLow ?? false;
        $eggsTotal = $eggsTotal ?? 0;
        $eggTrays = $eggTrays ?? 0;
        $vitamins = $vitamins ?? 0;
        $vitaminMin = $vitaminMin ?? 0;
        $vitaminLow = $vitaminLow ?? false;
        $overviewSubtitle = $overviewSubtitle ?? "Welcome back! Here's today's poultry farm overview.";
        $eggsTitle = $eggsTitle ?? 'Total Eggs Today';
        $predictedSubtitle = $predictedSubtitle ?? 'Next-day forecast · tap for sizes';
        $activeBatchesSubtitle = $activeBatchesSubtitle ?? 'Currently producing';
    @endphp

    <div class="dashboard-header">
        <div>
            <h2>Staff Dashboard</h2>
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

    <div class="stats-grid stats-grid--three">
        <div class="stat-card stat-card--eggs">
            <div class="stat-icon">
                <i class="fas fa-egg"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">{{ $eggsTitle }}</span>
                <h2>{{ number_format($eggsToday) }}</h2>
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
                <small>{{ $predictedSubtitle }}</small>
            </div>
        </div>

        <div class="stat-card stat-card--inventory">
            <div class="stat-icon">
                <i class="fas fa-dove"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Active Batches</span>
                <h2>{{ $activeBatches }}</h2>
                <small>{{ $activeBatchesSubtitle }}</small>
            </div>
        </div>
    </div>

    <div class="content-grid">
        @include('dashboard.my-activity-panel')

        @include('dashboard.inventory-levels-panel')
    </div>

    <x-forecast-summary-modal
        :forecast-summary="$forecastSummary ?? null"
        :previous-forecast-summary="$previousForecastSummary ?? null" />

@endsection
