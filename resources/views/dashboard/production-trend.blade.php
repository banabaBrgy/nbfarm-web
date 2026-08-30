@php
    $productionTrend = $productionTrend ?? ['months' => [], 'years' => [], 'selectedYear' => null, 'series' => []];
    $trendAction = $trendAction ?? url()->current();
@endphp

<div class="chart-card trend-chart-card">
    <div class="trend-chart-header">
        <div>
            <div class="chart-title" style="margin-bottom:4px;">Egg production trend</div>
            <p class="trend-chart-hint">Monthly harvest by year — see if production is going up or down.</p>
        </div>

        <form method="GET" action="{{ $trendAction }}" class="trend-year-filter">
            @foreach(request()->except('trend_year') as $name => $value)
                @if(! is_array($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endif
            @endforeach

            <label for="trendYear">Year</label>
            <select id="trendYear" name="trend_year" onchange="this.form.submit()">
                <option value="">All years</option>
                @foreach($productionTrend['years'] as $year)
                    <option value="{{ $year }}" @selected((int) $productionTrend['selectedYear'] === (int) $year)>
                        {{ $year }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="chart-wrap trend-chart-wrap">
        <canvas id="productionTrendChart" height="260"></canvas>
    </div>
</div>
