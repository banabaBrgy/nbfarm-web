@php
    $rolePrefix = \App\Models\User::routeRole();
    $indexRoute = $rolePrefix . '.production';
    $scanRoute = $rolePrefix . '.production.scan';
    $generateRoute = $rolePrefix . '.qr.generate';
    $detailsRoute = $rolePrefix . '.production.details';
    $canManageQr = $rolePrefix === 'staff';
    $date = $selectedDate instanceof \Carbon\Carbon
        ? $selectedDate
        : \Carbon\Carbon::parse($selectedDate);
@endphp

<div class="production-page">

    <div class="page-header">
        <div>
            <h2>Production</h2>
            <p>Track today's egg harvest, batches, and forecasts.</p>
        </div>
        @if($canManageQr)
            <div class="page-header-actions">
                <form action="{{ route($generateRoute) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-update">
                        <i class="fas fa-qrcode"></i>
                        Generate QR
                    </button>
                </form>
                <a href="{{ route($scanRoute) }}" class="btn-update">
                    <i class="fas fa-camera"></i>
                    Scan QR
                </a>
            </div>
        @endif
    </div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="stats-grid {{ $canManageQr ? '' : 'stats-grid--three' }}">

    <div class="stat-card stat-card--eggs">
        <div class="stat-icon">
            <i class="fas fa-egg"></i>
        </div>
        <div class="stat-info">
            <span class="stat-title">Today's Production</span>
            <h2>{{ number_format($totalEggs) }}</h2>
            <small>Eggs produced</small>
        </div>
    </div>

    <div class="stat-card stat-card--inventory">
        <div class="stat-icon">
            <i class="fas fa-layer-group"></i>
        </div>
        <div class="stat-info">
            <span class="stat-title">Active Batches</span>
            <h2>{{ $productions->count() }}</h2>
            <small>Available today</small>
        </div>
    </div>

    <div class="stat-card stat-card--predicted">
        <button
            type="button"
            class="stat-icon border-0"
            data-bs-toggle="modal"
            data-bs-target="#forecastHistoryModal"
            aria-label="View forecast history">
            <i class="fas fa-chart-line"></i>
        </button>
        <div class="stat-info">
            <span class="stat-title">Predicted Eggs</span>
            <h2>{{ number_format($predictedEggs) }}</h2>
            <small>Next-day forecast</small>
        </div>
    </div>

</div>

<div class="table-container">

    <div class="table-title d-flex justify-content-between align-items-center">
        <h2>Today's Egg Production</h2>
        <form method="GET" action="{{ route($indexRoute) }}" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search Batch"
                value="{{ request('search') }}">
            <input
                type="date"
                name="date"
                class="form-control"
                min="{{ \App\Models\Production::sampleStartDate() }}"
                max="{{ now()->toDateString() }}"
                value="{{ $date->toDateString() }}"
                data-auto-submit>
        </form>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Batch</th>
                <th>Small</th>
                <th>Medium</th>
                <th>Large</th>
                <th>Extra Large</th>
                <th>Cracked</th>
                <th>Total</th>
                <th width="150">Action</th>
            </tr>
        </thead>
        <tbody>
            @php
                $small = 0;
                $medium = 0;
                $large = 0;
                $xl = 0;
                $cracked = 0;
                $total = 0;
            @endphp

            @forelse($productions as $production)
                @php
                    $small += $production->small_eggs;
                    $medium += $production->medium_eggs;
                    $large += $production->large_eggs;
                    $xl += $production->extra_large_eggs;
                    $cracked += $production->cracked_eggs;
                    $total += $production->totalEggCount();
                @endphp
                <tr>
                    <td>
                        <strong>Batch {{ $production->batch_id }}</strong>
                    </td>
                    <td><x-egg-count :value="$production->small_eggs" /></td>
                    <td><x-egg-count :value="$production->medium_eggs" /></td>
                    <td><x-egg-count :value="$production->large_eggs" /></td>
                    <td><x-egg-count :value="$production->extra_large_eggs" /></td>
                    <td><x-egg-count :value="$production->cracked_eggs" /></td>
                    <td>
                        <strong>
                            <x-egg-count :value="$production->totalEggCount()" />
                        </strong>
                    </td>
                    <td>
                        <a href="{{ route($detailsRoute, $production->production_id) }}"
                            class="btn btn-success btn-sm">
                            <i class="fas fa-eye"></i>
                            View / Edit
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-muted text-center py-4">
                        No harvest records for this date.
                    </td>
                </tr>
            @endforelse

            @if($productions->isNotEmpty())
                <tr class="table-success">
                    <th>TOTAL</th>
                    <th><x-egg-count :value="$small" /></th>
                    <th><x-egg-count :value="$medium" /></th>
                    <th><x-egg-count :value="$large" /></th>
                    <th><x-egg-count :value="$xl" /></th>
                    <th><x-egg-count :value="$cracked" /></th>
                    <th><x-egg-count :value="$total" /></th>
                    <th>-</th>
                </tr>
            @endif
        </tbody>
    </table>

</div>

<div class="modal fade" id="forecastHistoryModal" tabindex="-1" aria-labelledby="forecastHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="forecastHistoryLabel">Forecast History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Previous daily forecasts.</p>
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Predicted Eggs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($forecastHistory as $forecast)
                            <tr>
                                <td>{{ $forecast->forecast_date->format('M j, Y') }}</td>
                                <td class="text-end">{{ number_format($forecast->predicted_eggs) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">No forecast history yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($forecastHistory->hasPages())
                    <div class="forecast-history-pages mt-3">
                        {{ $forecastHistory->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if(request()->boolean('forecast_open'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('forecastHistoryModal');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
</script>
@endif

</div>
