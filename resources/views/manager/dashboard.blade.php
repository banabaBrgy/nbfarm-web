@extends('layouts.sidebar')

@section('content')
@section('title', 'Dashboard')
@section('page-label', 'Manager Dashboard')

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/manager.dashboard.css') }}">
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
        $supplements = $supplements ?? 0;

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



    @if($lowStockAlert)

        <div class="alert">
            <span>⚠️</span>
            <span>
                <strong>Low stock alert</strong>
                — {{ $lowStockAlert }} item(s)
            </span>
        </div>

    @endif




    <div class="stats-grid stats-grid--five">


        {{-- TODAY EGGS --}}
        <div class="stat-card">

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

                <small>
                    {{ $eggsChange >= 0 ? '+' : '-' }}{{ abs($eggsChange) }}% vs yesterday
                </small>

            </div>

        </div>

        {{-- Predicted eggs (Python linear regression) --}}
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Predicted Eggs</span>
                <h2>{{ number_format($predictedEggs) }}</h2>
                <small>Next-day forecast</small>
            </div>
        </div>

        {{-- FEEDS --}}
        <div class="stat-card">

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

            <div class="stat-card">

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

            <div class="stat-card">

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
                        Supplements
                    </span>


                    <h2>
                        {{ number_format($supplements) }}
                    </h2>


                    <small>
                        Available
                    </small>


                </div>

            </div>


        @endif


    </div>





    <div class="content-grid">


        <div class="chart-card">

            <div class="chart-title">
                Egg Production — Last 7 Days + Forecast
            </div>


            <div class="chart-wrap">

                <canvas id="eggChart" height="200"></canvas>

            </div>


        </div>





        <div class="panel-card">


            <div class="panel-title">
                Inventory Levels
            </div>



            <div class="inv-row">

                <div class="inv-label">

                    <span>
                        Eggs
                    </span>

                    <span>
                        {{ number_format($eggsTotal) }} pcs
                    </span>

                </div>


                <div class="inv-bar">

                    <div class="inv-fill" style="width: {{ min(100, $eggsTotal / 150) }}%">
                    </div>

                </div>


            </div>





            <div class="inv-row">

                <div class="inv-label">

                    <span>
                        Feed (sack)
                    </span>


                    <span class="{{ $feedLow ? 'low-text' : '' }}">

                        {{ $feedStock }}

                        @if($feedLow)
                            — below min
                        @endif

                    </span>


                </div>


                <div class="inv-bar">

                    <div class="inv-fill {{ $feedLow ? 'fill-low' : '' }}"
                        style="width: {{ min(100, ($feedStock / max($feedMin, 1)) * 50) }}%">
                    </div>

                </div>


            </div>





            <div class="inv-row">

                <div class="inv-label">

                    <span>
                        Egg Trays
                    </span>

                    <span>
                        {{ number_format($eggTrays) }} pcs
                    </span>


                </div>


                <div class="inv-bar">

                    <div class="inv-fill" style="width: {{ min(100, $eggTrays / 5) }}%">
                    </div>

                </div>


            </div>




            <div class="inv-row">

                <div class="inv-label">

                    <span>
                        Supplements
                    </span>

                    <span>
                        {{ number_format($supplements) }} pack
                    </span>


                </div>


                <div class="inv-bar">

                    <div class="inv-fill" style="width: {{ min(100, $supplements) }}%">
                    </div>

                </div>


            </div>



        </div>


    </div>

    @include('dashboard.production-trend', [
        'productionTrend' => $productionTrend,
        'trendAction' => route('manager.dashboard'),
    ])

@endsection



@push('scripts')

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>


    <script>

        const ctx = document
            .getElementById('eggChart')
            .getContext('2d');


        const labels = @json($eggChartLabels);

        const barData = @json($eggChartData);

        const lineData = @json($eggForecastData);



        new Chart(ctx, {

            data: {

                labels: labels,


                datasets: [

                    {
                        type: 'bar',
                        label: 'Actual',
                        data: barData,
                        backgroundColor: "#2e7d32",
                        borderRadius: 10,
                        barThickness: 32
                    },


                    {
                        type: 'line',
                        label: 'Forecast',
                        data: lineData,
                        borderColor: '#e07a3f',
                        borderDash: [6, 4],
                        borderWidth: 2,
                        fill: false,
                        tension: 0.3
                    }

                ]

            },


            options: {

                responsive: true,

                maintainAspectRatio: false,


                plugins: {

                    legend: {
                        display: true,
                        position: 'top'
                    }

                },


                scales: {

                    y: {
                        display: false
                    },

                    x: {
                        grid: {
                            display: false
                        }

                    }

                }


            }


        });


    </script>
    @include('dashboard.production-trend-script')


@endpush