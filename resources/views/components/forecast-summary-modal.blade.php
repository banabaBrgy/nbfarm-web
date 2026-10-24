@props([
    'forecastSummary' => null,
    'previousForecastSummary' => null,
    'showHistory' => false,
])

@php
    $forecastSummary = $forecastSummary ?? [
        'predicted_eggs' => 0,
        'forecast_date' => now()->addDay()->toDateString(),
        'temperature' => null,
        'history_years' => 3,
        'history_start' => '2023-01-01',
        'history_end' => now()->subDay()->toDateString(),
        'sample_days' => 0,
        'engine' => 'python',
        'sizes' => [],
    ];
    $previousForecastSummary = $previousForecastSummary ?? $forecastSummary;
    $tempLabel = $forecastSummary['temperature'] !== null
        ? number_format((float) $forecastSummary['temperature'], 1).'°C'
        : 'not available';
    $previousTempLabel = $previousForecastSummary['temperature'] !== null
        ? number_format((float) $previousForecastSummary['temperature'], 1).'°C'
        : 'not available';
@endphp

<div class="modal fade" id="forecastSummaryModal" tabindex="-1" aria-labelledby="forecastSummaryLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered forecast-summary-dialog">
        <div class="modal-content forecast-summary-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="forecastSummaryLabel">Forecast Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="forecast-summary-total">
                    <span>Next-day total</span>
                    <strong>{{ number_format($forecastSummary['predicted_eggs']) }} eggs</strong>
                    <small>
                        For {{ \Carbon\Carbon::parse($forecastSummary['forecast_date'])->format('M j, Y') }}
                        · {{ $tempLabel }}
                    </small>
                </div>

                <table class="table mb-0 forecast-summary-table">
                    <colgroup>
                        <col class="col-category">
                        <col class="col-eggs">
                        <col class="col-trays">
                        <col class="col-share">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col" class="text-center">Eggs</th>
                            <th scope="col" class="text-center">Trays</th>
                            <th scope="col" class="text-center">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($forecastSummary['sizes'] as $size)
                            <tr>
                                <td class="forecast-summary-category">{{ $size['label'] }}</td>
                                <td class="text-center">{{ number_format($size['eggs']) }}</td>
                                <td class="text-center">{{ number_format($size['trays']) }}</td>
                                <td class="text-center">{{ number_format($size['share'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No forecast summary yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm me-auto"
                    data-bs-dismiss="modal"
                    data-bs-toggle="modal"
                    data-bs-target="#previousDayModal">
                    Previous Day
                </button>
                @if($showHistory)
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        data-bs-dismiss="modal"
                        data-bs-toggle="modal"
                        data-bs-target="#forecastHistoryModal">
                        View forecast history
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="previousDayModal" tabindex="-1" aria-labelledby="previousDayLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered forecast-summary-dialog">
        <div class="modal-content forecast-summary-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="previousDayLabel">Previous Day</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="forecast-summary-total">
                    <span>Forecast total</span>
                    <strong>{{ number_format($previousForecastSummary['predicted_eggs']) }} eggs</strong>
                    <small>
                        For {{ \Carbon\Carbon::parse($previousForecastSummary['forecast_date'])->format('M j, Y') }}
                        · {{ $previousTempLabel }}
                    </small>
                </div>

                <table class="table mb-0 forecast-summary-table">
                    <colgroup>
                        <col class="col-category">
                        <col class="col-eggs">
                        <col class="col-trays">
                        <col class="col-share">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col" class="text-center">Eggs</th>
                            <th scope="col" class="text-center">Trays</th>
                            <th scope="col" class="text-center">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($previousForecastSummary['sizes'] as $size)
                            <tr>
                                <td class="forecast-summary-category">{{ $size['label'] }}</td>
                                <td class="text-center">{{ number_format($size['eggs']) }}</td>
                                <td class="text-center">{{ number_format($size['trays']) }}</td>
                                <td class="text-center">{{ number_format($size['share'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No previous forecast summary yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    ['forecastSummaryModal', 'previousDayModal', 'forecastHistoryModal'].forEach(function (id) {
        var modal = document.getElementById(id);
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });
});
</script>
@endpush
