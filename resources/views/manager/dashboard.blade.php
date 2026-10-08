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
        $eggsChange = $eggsChange ?? 0;

        $feedStock = $feedStock ?? 0;
        $feedMin = $feedMin ?? 0;
        $feedLow = $feedLow ?? false;

        $dispatchedTrays = $dispatchedTrays ?? 0;
        $pendingDeliveries = $pendingDeliveries ?? 0;

        $revenueToday = $revenueToday ?? 0;
        $salesThisMonth = $salesThisMonth ?? 0;

        $eggsTotal = $eggsTotal ?? 0;

        $eggTrays = $eggTrays ?? 0;
        $vitamins = $vitamins ?? 0;
        $vitaminMin = $vitaminMin ?? 0;
        $vitaminLow = $vitaminLow ?? false;

        $lowStockAlert = $lowStockAlert ?? null;

        $eggChartLabels = $eggChartLabels ?? [];
        $eggChartData = $eggChartData ?? [];

        $eggForecastLabels = $eggForecastLabels ?? [];
        $eggForecastData = $eggForecastData ?? [];

        $predictedEggs = $predictedEggs ?? 0;
        $productionTrend = $productionTrend ?? ['months' => [], 'years' => [], 'selectedYear' => null, 'series' => []];

    @endphp



    <div class="dashboard-header">
        <div>
            <h2>Manager Dashboard</h2>
            <p>Welcome back! Here's today's poultry farm overview.</p>
        </div>
        <x-weather-widget />
    </div>



    @include('dashboard.low-stock-alert')

        
    




    <div class="stats-grid stats-grid--six">


        {{-- TODAY EGGS --}}
        <div class="stat-card stat-card--eggs">

            <div class="stat-icon">
                <i class="fas fa-egg"></i>
            </div>

            <div class="stat-info">

                <span class="stat-title">
                    Total Eggs Today
                </span>

                <h2>
                    {{ number_format($eggsToday) }}
                </h2>

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

        {{-- FEEDS --}}
        <div class="stat-card stat-card--feed">

            <div class="stat-icon">
                <i class="fas fa-wheat-awn"></i>
            </div>


            <div class="stat-info">

                <span class="stat-title">
                    Feeds Available
                </span>


                <h2>
                    {{ number_format($feedStock) }}
                </h2>


                <small>

                    @if($feedLow)

                        Low Stock

                    @else

                        Bags Remaining

                    @endif

                </small>

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


                    <small>
                        {{ $pendingDeliveries }} Pending
                    </small>


                </div>

            </div>




            {{-- REVENUE --}}

            <div class="stat-card stat-card--revenue">

                <div class="stat-icon">
                    <i class="fas fa-peso-sign"></i>
                </div>


                <div class="stat-info">


                    <span class="stat-title">
                        Revenue Today
                    </span>


                    <h2>
                        ₱{{ number_format($revenueToday, 0) }}
                    </h2>


                    <small>
                        Today's Income
                    </small>


                </div>

            </div>

            <a
                href="{{ route('manager.sales.history') }}"
                class="stat-card stat-card--revenue stat-card--clickable"
                aria-label="View sales history">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-title">This Month's Sales</span>
                    <h2>₱{{ number_format($salesThisMonth, 2) }}</h2>
                    <small>Tap to view all sales</small>
                </div>
            </a>



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





    <div class="content-grid">


        @include('dashboard.egg-production-chart-card', [
            'productionTrend' => $productionTrend,
            'trendAction' => route('manager.dashboard'),
        ])





        @include('dashboard.inventory-levels-panel')


    </div>

    <x-forecast-summary-modal :forecast-summary="$forecastSummary ?? null" />

@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    @include('dashboard.production-trend-script')
@endpush