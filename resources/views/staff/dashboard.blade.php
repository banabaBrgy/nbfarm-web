@extends('layouts.sidebar')

@section('title', 'Dashboard')

@section('content')

    @php
        $eggsChange = $eggsChange ?? 0;
    @endphp

    <div class="dashboard-header">
        <div>
            <h2>Dashboard</h2>
            <p>Welcome back! Here's today's poultry farm overview.</p>
        </div>
        <x-weather-widget />
    </div>

    <div class="stats-grid stats-grid--five">

        <div class="stat-card stat-card--eggs">
            <div class="stat-icon">
                <i class="fas fa-egg"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Total Eggs Today</span>
                <h2>{{ number_format($todayProduction) }}</h2>
                <small class="{{ $eggsChange >= 0 ? 'stat-trend-up' : 'stat-trend-down' }}">
                    {{ $eggsChange >= 0 ? '+' : '-' }}{{ abs($eggsChange) }}% vs yesterday
                </small>
            </div>
        </div>

        <div class="stat-card stat-card--predicted">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Predicted Eggs</span>
                <h2>{{ number_format($predictedEggs ?? 0) }}</h2>
                <small>Next-day forecast</small>
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
                <h2>{{ number_format($feeds) }}</h2>
                <small>Bags remaining</small>
            </div>
        </div>

        <div class="stat-card stat-card--inventory">
            <div class="stat-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-info">
                <span class="stat-title">Egg Trays</span>
                <h2>{{ number_format($eggTrays) }}</h2>
                <small>Available trays</small>
            </div>
        </div>

    </div>

    <div class="chart-card">
        <div class="chart-title">Egg Production — Last 7 Days</div>
        <div class="chart-wrap">
            <canvas id="weeklyChart" height="200"></canvas>
        </div>
    </div>

    <div class="quick-menu">
        <a href="{{ route('staff.production') }}" class="quick-card">
            <i class="fas fa-egg"></i>
            <span>Record Production</span>
        </a>
        <a href="{{ route('staff.inventory') }}" class="quick-card">
            <i class="fas fa-box"></i>
            <span>View Inventory</span>
        </a>
        <a href="{{ route('staff.dashboard') }}" class="quick-card">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
    </div>

@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <script>
        (function () {
            const canvas = document.getElementById('weeklyChart');
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            const labels = @json($weeklyProduction->map(fn ($row) => \Illuminate\Support\Carbon::parse($row['production_date'])->format('M j')));
            const values = @json($weeklyProduction->pluck('total'));

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Eggs produced',
                        data: values,
                        backgroundColor: '#34853F',
                        borderRadius: 10,
                        barThickness: 32,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(15, 23, 42, 0.06)' },
                        },
                        x: {
                            grid: { display: false },
                        },
                    },
                },
            });
        })();
    </script>
@endpush
