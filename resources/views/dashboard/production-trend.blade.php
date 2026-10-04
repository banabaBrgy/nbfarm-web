@php
    $productionTrend = $productionTrend ?? ['months' => [], 'years' => [], 'selectedYear' => null, 'series' => []];
    $trendAction = $trendAction ?? url()->current();
@endphp

<form method="GET" action="{{ $trendAction }}" class="trend-year-filter egg-production-chart__filter">
    @foreach(request()->except('trend_year') as $name => $value)
        @if(! is_array($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <label for="trendYear">View</label>
    <select id="trendYear" name="trend_year" data-auto-submit>
        <option value="">Week + 3-year forecast</option>
        @foreach(($productionTrend['years'] ?? [2023, 2024, 2025, 2026]) as $year)
            <option value="{{ $year }}" @selected((int) $productionTrend['selectedYear'] === $year)>
                {{ $year }}
            </option>
        @endforeach
    </select>
</form>
