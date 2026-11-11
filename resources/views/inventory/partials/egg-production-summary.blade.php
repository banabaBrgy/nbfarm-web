{{-- Egg Production Summary: month/year update this table in place (see public/js/egg-summary.js). --}}
<div class="inventory-table-wrapper" id="eggProductionSummary">
    <div class="egg-summary-filter" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h2 class="section-title" style="margin:0;">Egg Production Summary</h2>

        <form method="GET" class="egg-summary-form" data-egg-summary-form style="display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="status" value="{{ $statusFilter }}">

            <select name="year" class="sort-select" aria-label="Production year">
                @foreach(($availableYears ?? $availableMonths->pluck('year')->unique()->sortDesc()) as $year)
                    <option value="{{ $year }}" {{ (int) $year === (int) $selectedYear ? 'selected' : '' }}>
                        {{ $year }}
                    </option>
                @endforeach
            </select>

            <select name="month" class="sort-select" aria-label="Production month">
                @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}" {{ (int) $m === (int) $selectedMonth ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <table class="inventory-table">
        <thead>
            <tr>
                <th>Egg Size</th>
                <th>Total Produced</th>
                <th>% of Total</th>
            </tr>
        </thead>
        <tbody data-egg-summary-body>
            @foreach($summaryRows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td><strong>{{ $row['value'] }}</strong></td>
                <td>
                    <div class="mini-bar">
                        <div class="mini-bar-fill" style="width: {{ $row['percent'] }}%"></div>
                    </div>
                    <span class="mini-bar-label">{{ $row['percent'] }}%</span>
                </td>
            </tr>
            @endforeach

            <tr class="table-total-row">
                <td><strong>TOTAL</strong></td>
                <td><strong>{{ number_format($grandTotal) }}</strong></td>
                <td>100%</td>
            </tr>
        </tbody>
    </table>

    <p class="egg-summary-error" data-egg-summary-status hidden></p>
</div>

@push('scripts')
    <script src="{{ versioned_asset('js/egg-summary.js') }}"></script>
@endpush
