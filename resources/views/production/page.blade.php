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

<div class="stats-grid stats-grid--three">

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

</div>

<div class="table-container production-table-panel">

    <div class="table-title production-table-toolbar">
        <h2>Today's Egg Production</h2>
        <form method="GET" action="{{ route($indexRoute) }}" class="production-table-filters">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search Batch"
                value="{{ request('search') }}"
                aria-label="Search Batch">
            <input
                type="date"
                name="date"
                class="form-control"
                min="{{ \App\Models\Production::sampleStartDate() }}"
                max="{{ now()->toDateString() }}"
                value="{{ $date->toDateString() }}"
                data-auto-submit
                aria-label="Production date">
        </form>
    </div>

    <div class="production-table-scroll">
        <table class="table production-table has-action-column">
            <thead>
                <tr>
                    <th class="col-batch">Batch</th>
                    <th class="col-num">Small</th>
                    <th class="col-num">Medium</th>
                    <th class="col-num">Large</th>
                    <th class="col-num" title="Extra Large">XL</th>
                    <th class="col-num">Cracked</th>
                    <th class="col-num col-total">Total</th>
                    <th class="col-action">Action</th>
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
                        <td class="col-batch">
                            <strong>Batch {{ $production->batch_id }}</strong>
                        </td>
                        <td class="col-num"><x-egg-count :value="$production->small_eggs" /></td>
                        <td class="col-num"><x-egg-count :value="$production->medium_eggs" /></td>
                        <td class="col-num"><x-egg-count :value="$production->large_eggs" /></td>
                        <td class="col-num"><x-egg-count :value="$production->extra_large_eggs" /></td>
                        <td class="col-num"><x-egg-count :value="$production->cracked_eggs" /></td>
                        <td class="col-num col-total">
                            <strong>
                                <x-egg-count :value="$production->totalEggCount()" />
                            </strong>
                        </td>
                        <td class="col-action">
                            <a href="{{ route($detailsRoute, $production->production_id) }}"
                                class="btn btn-success btn-sm production-action-btn"
                                title="View or edit batch">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                                <span>View</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="production-table-empty">
                            No harvest records for this date.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($productions->isNotEmpty())
                <tfoot>
                    <tr class="table-success production-table-total">
                        <th class="col-batch">Total</th>
                        <th class="col-num"><x-egg-count :value="$small" /></th>
                        <th class="col-num"><x-egg-count :value="$medium" /></th>
                        <th class="col-num"><x-egg-count :value="$large" /></th>
                        <th class="col-num"><x-egg-count :value="$xl" /></th>
                        <th class="col-num"><x-egg-count :value="$cracked" /></th>
                        <th class="col-num col-total"><x-egg-count :value="$total" /></th>
                        <th class="col-action"></th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

</div>

<x-forecast-summary-modal :forecast-summary="$forecastSummary ?? null" :show-history="true" />

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
                    <div class="forecast-history-pagination">
                        <div class="forecast-history-pagination-info">
                            Showing {{ $forecastHistory->firstItem() }}–{{ $forecastHistory->lastItem() }} of {{ $forecastHistory->total() }}
                        </div>
                        <div class="forecast-history-pagination-controls">
                            @if ($forecastHistory->onFirstPage())
                                <span class="forecast-history-page-btn disabled">Prev</span>
                            @else
                                <a href="{{ $forecastHistory->previousPageUrl() }}" class="forecast-history-page-btn">Prev</a>
                            @endif

                            <span class="forecast-history-page-number">
                                Page {{ $forecastHistory->currentPage() }} of {{ $forecastHistory->lastPage() }}
                            </span>

                            @if ($forecastHistory->hasMorePages())
                                <a href="{{ $forecastHistory->nextPageUrl() }}" class="forecast-history-page-btn">Next</a>
                            @else
                                <span class="forecast-history-page-btn disabled">Next</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    data-bs-dismiss="modal"
                    data-bs-toggle="modal"
                    data-bs-target="#forecastSummaryModal">
                    Back to forecast summary
                </button>
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
