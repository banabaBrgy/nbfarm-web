@php
    $eggProductionChart = $eggProductionChart ?? [
        'view' => 'forecast',
        'needsDates' => false,
        'startDate' => null,
        'endDate' => null,
    ];

    $trendAction = $trendAction ?? url()->current();
    $selectedView = $eggProductionChart['view'] ?? 'forecast';
    $showDates = (bool) ($eggProductionChart['needsDates'] ?? false);
@endphp

<form method="GET"
      action="{{ $trendAction }}"
      class="trend-custom-filter">

    @foreach(request()->except([
        'trend_year',
        'trend_view',
        'start_date',
        'end_date'
    ]) as $name => $value)
        @if(! is_array($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="trend-view-select">
        <label for="trendView">View</label>

        <select
            id="trendView"
            name="trend_view"
            onchange="submitTrendView(this)"
        >
            <option value="historical" @selected($selectedView === 'historical')>
                Historical Production
            </option>

            <option value="comparison" @selected($selectedView === 'comparison')>
                Actual vs. Forecast
            </option>

            <option value="timeseries" @selected($selectedView === 'timeseries')>
                Time Series Forecast
            </option>

            <option value="forecast" @selected($selectedView === 'forecast')>
                Seven-Day Forecast
            </option>
        </select>
    </div>

    <div
        id="trendDateRange"
        class="trend-date-range"
        style="{{ $showDates ? '' : 'display:none;' }}"
    >
        <div class="trend-date-field">
            <label for="startDate">Start Date</label>
            <input
                type="date"
                id="startDate"
                name="start_date"
                value="{{ $showDates ? old('start_date', $eggProductionChart['startDate'] ?? '') : '' }}"
                {{ $showDates ? '' : 'disabled' }}
            >
        </div>

        <div class="trend-date-field">
            <label for="endDate">End Date</label>
            <input
                type="date"
                id="endDate"
                name="end_date"
                value="{{ $showDates ? old('end_date', $eggProductionChart['endDate'] ?? '') : '' }}"
                {{ $showDates ? '' : 'disabled' }}
            >
        </div>

        <button type="submit" class="trend-apply-button">
            Apply Filter
        </button>
    </div>
</form>

@error('start_date')
    <p class="trend-date-error">{{ $message }}</p>
@enderror
@error('end_date')
    <p class="trend-date-error">{{ $message }}</p>
@enderror

<style>
    .trend-custom-filter {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 12px;
    }

    .trend-view-select,
    .trend-date-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .trend-view-select label,
    .trend-date-field label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
    }

    .trend-view-select select,
    .trend-date-field input {
        height: 40px;
        padding: 0 12px;
        border: 1px solid #d1ded5;
        border-radius: 8px;
        background: #fff;
        color: #244b36;
        font-size: 13px;
        outline: none;
    }

    .trend-view-select select {
        min-width: 210px;
    }

    .trend-date-field input {
        min-width: 145px;
    }

    .trend-view-select select:focus,
    .trend-date-field input:focus {
        border-color: #2e7d52;
        box-shadow: 0 0 0 3px rgba(46, 125, 82, 0.12);
    }

    .trend-date-range {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 10px;
    }

    .trend-apply-button {
        height: 40px;
        padding: 0 16px;
        border: none;
        border-radius: 8px;
        background: #256b46;
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .trend-apply-button:hover {
        background: #195536;
    }

    .trend-date-error {
        margin: 8px 0 0;
        font-size: 12px;
        font-weight: 600;
        color: #b45309;
    }

    @media (max-width: 768px) {
        .trend-custom-filter,
        .trend-date-range {
            width: 100%;
        }

        .trend-view-select,
        .trend-view-select select {
            width: 100%;
            min-width: 0;
        }

        .trend-date-field {
            flex: 1;
            min-width: 130px;
        }

        .trend-date-field input {
            width: 100%;
            min-width: 0;
        }
    }
</style>

<script>
    function submitTrendView(select) {
        const form = select.form;
        const start = document.getElementById('startDate');
        const end = document.getElementById('endDate');
        const showDates = select.value === 'historical' || select.value === 'comparison';

        if (start && end) {
            start.disabled = !showDates;
            end.disabled = !showDates;
        }

        if (form) {
            form.submit();
        }
    }
</script>
