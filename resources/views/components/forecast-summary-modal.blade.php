@props([
    'forecastSummary' => null,
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
    $tempLabel = $forecastSummary['temperature'] !== null
        ? number_format((float) $forecastSummary['temperature'], 1).'°C'
        : 'not available';
@endphp

<div class="modal fade" id="forecastSummaryModal" tabindex="-1" aria-labelledby="forecastSummaryLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col" class="text-end">Eggs</th>
                            <th scope="col" class="text-end">Trays</th>
                            <th scope="col" class="text-end">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($forecastSummary['sizes'] as $size)
                            <tr>
                                <td class="forecast-summary-category">{{ $size['label'] }}</td>
                                <td class="text-end">{{ number_format($size['eggs']) }}</td>
                                <td class="text-end">{{ number_format($size['trays']) }}</td>
                                <td class="text-end">{{ number_format($size['share'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No forecast summary yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($showHistory)
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        data-bs-dismiss="modal"
                        data-bs-toggle="modal"
                        data-bs-target="#forecastHistoryModal">
                        View forecast history
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
