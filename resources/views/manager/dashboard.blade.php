@extends('layouts.sidebar')

@section('content')
@section('title', 'Dashboard')
@section('page-label', 'Manager Dashboard')

    @push('styles')
        <link rel="stylesheet" href="{{ versioned_asset('css/dashboard.css') }}">
        <link rel="stylesheet" href="{{ versioned_asset('css/manager.dashboard.css') }}">
    @endpush


    @php

        $role = auth()->user()->role;

        $eggsToday = $eggsToday ?? 0;

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
        $overviewSubtitle = $overviewSubtitle ?? "Welcome back! Here's today's poultry farm overview.";
        $eggsTitle = $eggsTitle ?? (($dateFilter['isToday'] ?? true) ? 'Total Eggs Today' : 'Total Eggs');
        $predictedSubtitle = ($dateFilter['isToday'] ?? true)
            ? 'Next-day forecast · tap for stats.'
            : ($predictedSubtitle ?? 'Forecast · tap for stats.');
        $revenueTitle = $revenueTitle ?? 'Revenue Today';
        $revenueSubtitle = $revenueSubtitle ?? "Today's Income";

    @endphp



    <div class="dashboard-header">
        <div>
            <h2>Manager Dashboard</h2>
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

    <div class="stats-grid">


        {{-- EGG PRODUCTION --}}
        <div class="stat-card stat-card--eggs">

            <div class="stat-icon">
                <i class="fas fa-egg"></i>
            </div>

            <div class="stat-info">

                <span class="stat-title">
                    {{ $eggsTitle }}
                </span>

                <h2>
                    {{ number_format($eggsToday) }}
                </h2>

            </div>

        </div>

        {{-- Predicted eggs (Python ARIMA 2,1,1) --}}
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
                <span class="stat-title">predicted eggs</span>
                <h2>{{ number_format($predictedEggs) }}</h2>
                <small>{{ $predictedSubtitle }}</small>
            </div>
        </div>



        @if($role != 'staff')


            {{-- DISPATCH --}}

            <div class="stat-card stat-card--dispatch">

                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>


                <div class="stat-info">

                    <span class="stat-title">
                        Egg Trays Dispatched
                    </span>


                    <h2>
                        {{ number_format($dispatchedTrays) }}
                    </h2>


                    <div class="stat-meta">
                        <small>{{ $pendingDeliveries }} Pending</small>
                    </div>


                </div>

            </div>




            {{-- REVENUE --}}

            <div class="stat-card stat-card--revenue">

                <div class="stat-icon">
                    <i class="fas fa-peso-sign"></i>
                </div>


                <div class="stat-info">


                    <span class="stat-title">
                        {{ $revenueTitle }}
                    </span>


                    <h2>
                        ₱{{ number_format($revenueToday, 0) }}
                    </h2>

                    <small>{{ $revenueSubtitle }}</small>

                </div>

            </div>


        @else


            {{-- STAFF INVENTORY --}}

            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>


                <div class="stat-info">

                    <span class="stat-title">
                        Egg Trays
                    </span>


                    <h2>
                        {{ number_format($eggTrays) }}
                    </h2>


                    <small>
                        Available
                    </small>


                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fas fa-capsules"></i>
                </div>


                <div class="stat-info">

                    <span class="stat-title">
                        Vitamins
                    </span>


                    <h2>
                        {{ number_format($vitamins) }}
                    </h2>


                    <small>
                        {{ $vitaminLow ? 'Low Stock' : 'Available' }}
                    </small>


                </div>

            </div>


        @endif


    </div>

    <div class="content-grid manager-content-grid">

        @include('dashboard.pending-dispatches-panel', [
            'dispatchIndexRoute' => route('manager.dispatch'),
        ])

        @include('dashboard.inventory-levels-panel')


    </div>

    <x-forecast-summary-modal
        :forecast-summary="$forecastSummary ?? null"
        :previous-forecast-summary="$previousForecastSummary ?? null" />

@endsection
