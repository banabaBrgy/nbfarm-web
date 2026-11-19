@php
    $eggProductionChart = $eggProductionChart ?? [
        'view' => 'forecast',
        'period' => 'Seven-Day Forecast',
        'needsDates' => false,
        'rangeLocked' => false,
        'startDate' => null,
        'endDate' => null,
        'emptyMessage' => null,
        'notice' => null,
        'labels' => [],
        'datasets' => [],
    ];

    $trendAction = $trendAction ?? url()->current();
    $hasChart = ! empty($eggProductionChart['labels']);
@endphp

<div class="chart-card egg-production-card">
    <div class="trend-chart-header egg-production-chart__header">

        <div class="egg-production-chart__title-wrap">
            <span class="egg-production-chart__icon" aria-hidden="true">
                <i class="fas fa-egg"></i>
            </span>

            <h3 class="egg-production-chart__title">
                <span class="egg-production-chart__heading">
                    Egg Production
                </span>

                <span class="egg-production-chart__period">
                    {{ $eggProductionChart['period'] ?? 'Seven-Day Forecast' }}
                </span>
            </h3>
        </div>

        @include('dashboard.production-trend', [
            'eggProductionChart' => $eggProductionChart,
            'trendAction' => $trendAction,
        ])

    </div>

    @if(! empty($eggProductionChart['notice']))
        <p class="egg-production-chart__notice">{{ $eggProductionChart['notice'] }}</p>
    @endif

    @if(! empty($eggProductionChart['emptyMessage']))
        <p class="egg-production-chart__empty" role="status">{{ $eggProductionChart['emptyMessage'] }}</p>
    @endif

    @if($hasChart)
        <div class="chart-wrap egg-production-chart__wrap">
            <canvas id="eggChart" height="200"></canvas>
        </div>
    @endif
</div>

<style>
    .egg-production-chart__notice,
    .egg-production-chart__empty {
        margin: 12px 0 0;
        font-size: 13px;
        line-height: 1.45;
        color: #4b5563;
    }

    .egg-production-chart__empty {
        padding: 28px 8px 12px;
        text-align: center;
    }
</style>
