@extends('layouts.sidebar')

@section('title', 'Dashboard')

@section('content')

    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/staffdashboard.css') }}">
    @endpush
    <!-- SUMMARY -->

    <div class="summary">

        <div class="dashboard-card-small">
            <div class="card-icon">
                <i class="fas fa-egg"></i>
            </div>
            <div>
                <h5>Total Eggs Today</h5>
                <h2>{{ number_format($todayProduction) }}</h2>
                <small>Today's collected eggs</small>
            </div>
        </div>

        <div class="dashboard-card-small">
            <div class="card-icon">
                <i class="fas fa-dove"></i>
            </div>
            <div>
                <h5>Active Batches</h5>
                <h2>{{ $activeBatches }}</h2>
                <small>Currently producing</small>
            </div>
        </div>

        <div class="dashboard-card-small">
            <div class="card-icon">
                <i class="fas fa-wheat-awn"></i>
            </div>
            <div>
                <h5>Feeds Available</h5>
                <h2>{{ number_format($feeds) }}</h2>
                <small>Bags remaining</small>
            </div>
        </div>

        <div class="dashboard-card-small">
            <div class="card-icon">
                <i class="fas fa-box"></i>
            </div>
            <div>
                <h5>Egg Trays</h5>
                <h2>{{ number_format($eggTrays) }}</h2>
                <small>Available trays</small>
            </div>
        </div>

    </div>

    <!-- BAR GRAPH -->

    <div class="table-container">

        <div class="table-title">

            <h2>Weekly Egg Production</h2>

        </div>

        <div class="chart-wrapper">

            <canvas id="productionChart"></canvas>

        </div>

        <div class="graph-description">

            <h5>Production Overview</h5>

            <p>

                This chart shows the total egg production recorded over the last seven days.
                It allows the poultry staff and farm manager to quickly monitor daily production,
                identify trends, and detect sudden increases or decreases in egg output.

            </p>

        </div>

    </div>

    <!-- QUICK ACCESS -->

    <div class="table-container" style="margin-top:25px;">

        <div class="table-title">

            <h2>Quick Access</h2>

        </div>

        <div class="quick-menu">

            <a href="{{ route('staff.production') }}" class="quick-card">

                <i class="fas fa-egg"></i>

                <span>Production</span>

            </a>

            <a href="{{ route('staff.inventory') }}" class="quick-card">

                <i class="fas fa-boxes"></i>

                <span>Inventory</span>

            </a>


        </div>

    </div>

@endsection

@push('scripts')

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>

        const ctx = document.getElementById('productionChart');

        new Chart(ctx, {

            type: 'bar',

            data: {

                labels: {!! json_encode(
        $weeklyProduction->map(function ($item) {
            return \Carbon\Carbon::parse($item['production_date'])->format('D');
        })
    ) !!},

                datasets: [{

                    label: 'Egg Production',

                    data: {!! json_encode($weeklyProduction->pluck('total')) !!},
                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {

                        display: false

                    }

                },

                scales: {

                    y: {

                        beginAtZero: true

                    }

                }

            }

        });

    </script>

@endpush