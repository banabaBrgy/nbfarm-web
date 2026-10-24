@php
    $inventoryForecast = $inventoryForecast ?? ['supplies' => [], 'trays' => []];

    $qty = function ($value): string {
        $formatted = number_format((float) $value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    };
@endphp

<div class="modal-overlay" id="inventoryForecastModal" onclick="if (event.target === this) closeInventoryForecast()">
    <div class="modal-box inventory-forecast-modal" role="dialog" aria-labelledby="inventory-forecast-title" aria-modal="true">
        <div class="inventory-forecast-head">
            <div>
                <h3 id="inventory-forecast-title">Next 7 days</h3>
                <p>Expected use starting tomorrow. Feeds and vitamins follow the daily farm rates. Egg trays follow the egg forecast and reset each morning.</p>
            </div>
            <button type="button" class="forecast-close" onclick="closeInventoryForecast()">Close</button>
        </div>
        <div class="inventory-forecast-scroll">

        <div class="inventory-table-wrapper">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Daily use</th>
                        <th>7-day<br>consumption</th>
                        <th>Stock after 7 days</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventoryForecast['supplies'] as $row)
                        <tr>
                            <td>{{ $row['item'] }} ({{ $row['unit'] }})</td>
                            <td>
                                @if($row['resets_daily'])
                                    From egg forecast
                                @else
                                    {{ $qty($row['daily_use']) }}
                                @endif
                            </td>
                            <td>{{ $qty($row['consumption']) }}</td>
                            <td>
                                @if($row['resets_daily'])
                                    Resets each day
                                @else
                                    @if($row['stock_after'] < 0)
                                        {{ $qty(abs($row['stock_after'])) }} short
                                    @else
                                        {{ $qty($row['stock_after']) }}
                                    @endif
                                    @if($row['already_at_minimum'])
                                        <div class="forecast-flag">Current stock is already at the minimum</div>
                                    @elseif($row['reaches_minimum_on'])
                                        <div class="forecast-flag">Reaches minimum on {{ \Carbon\Carbon::parse($row['reaches_minimum_on'])->format('M j') }}</div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No inventory forecast yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="inventory-table-wrapper">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Predicted eggs</th>
                        <th>Trays needed</th>
                        <th>Tray left</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventoryForecast['trays'] as $day)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($day['date'])->format('D, M j') }}</td>
                            <td>{{ number_format($day['eggs']) }}</td>
                            <td>{{ number_format($day['trays_needed']) }}</td>
                            <td>
                                {{ $qty($day['trays_left']) }}
                                @if($day['below_minimum'])
                                    <div class="forecast-flag">At or under minimum</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No tray forecast yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function ensureInventoryForecastOnBody() {
    var modal = document.getElementById('inventoryForecastModal');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    return modal;
}

function openInventoryForecast() {
    var modal = ensureInventoryForecastOnBody();
    if (!modal) {
        return;
    }
    modal.classList.add('active');
    document.body.classList.add('inventory-forecast-open');
}

function closeInventoryForecast() {
    var modal = document.getElementById('inventoryForecastModal');
    if (!modal) {
        return;
    }
    modal.classList.remove('active');
    document.body.classList.remove('inventory-forecast-open');
}

document.addEventListener('DOMContentLoaded', function () {
    ensureInventoryForecastOnBody();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeInventoryForecast();
    }
});
</script>
@endpush
