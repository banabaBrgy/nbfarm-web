@php
    $productionTrend = $productionTrend ?? ['months' => [], 'years' => [], 'selectedYear' => null, 'series' => []];
    $trendAction = $trendAction ?? url()->current();
@endphp

<div class="chart-card egg-production-card">
    <div class="trend-chart-header egg-production-chart__header">
        <div class="egg-production-chart__title-wrap">
            <span class="egg-production-chart__icon" aria-hidden="true">
                <i class="fas fa-egg"></i>
            </span>
            <h3 class="egg-production-chart__title">
                @if(! empty($productionTrend['selectedYear']))
                    Egg Production — {{ $productionTrend['selectedYear'] }}
                @else
                    Egg Production — Last 7 Days + Forecast
                @endif
            </h3>
        </div>
        @include('dashboard.production-trend', [
            'productionTrend' => $productionTrend,
            'trendAction' => $trendAction,
        ])
    </div>

    @if(empty($productionTrend['selectedYear']))
        <div class="egg-production-chart__legend" role="group" aria-label="Chart legend">
            <div class="egg-chart-legend">
                <button type="button" class="egg-chart-legend__item" data-egg-chart-toggle="Forecast" aria-pressed="true">
                    <span class="egg-chart-legend__mark egg-chart-legend__mark--forecast" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.5 5.5V2.5H5.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M10.5 2.5H13.5V5.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M13.5 10.5V13.5H10.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M5.5 13.5H2.5V10.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="egg-chart-legend__label">Forecast</span>
                </button>
                <button type="button" class="egg-chart-legend__item" data-egg-chart-toggle="Actual" aria-pressed="true">
                    <span class="egg-chart-legend__mark egg-chart-legend__mark--actual" aria-hidden="true"></span>
                    <span class="egg-chart-legend__label">Actual</span>
                </button>
            </div>
        </div>
    @endif

    <div class="chart-wrap egg-production-chart__wrap">
        <canvas id="eggChart" height="200"></canvas>
    </div>
</div>
