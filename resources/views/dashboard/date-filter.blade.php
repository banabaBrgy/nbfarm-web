@php
    $dateFilter = $dateFilter ?? [
        'period' => 'today',
        'date' => now()->toDateString(),
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
        'isToday' => true,
        'isRange' => false,
        'maxDate' => now()->toDateString(),
        'asOfLabel' => now()->format('M j, Y'),
    ];
    $period = $dateFilter['period'] ?? 'today';
@endphp

<div class="dashboard-date-filter-wrap">
<form
    method="GET"
    action="{{ url()->current() }}"
    class="dashboard-date-filter"
    id="dashboardDateFilter"
>
    @foreach(request()->except([
        'dash_period',
        'dash_date',
        'dash_from',
        'dash_to',
        'start_date',
        'end_date',
    ]) as $name => $value)
        @if(! is_array($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="dashboard-date-filter__modes" role="group" aria-label="Dashboard date period">
        <button type="button" data-period="today" class="{{ $period === 'today' ? 'is-on' : '' }}">Today</button>
        <button type="button" data-period="date" class="{{ $period === 'date' ? 'is-on' : '' }}">Date</button>
        <button type="button" data-period="range" class="{{ $period === 'range' ? 'is-on' : '' }}">Range</button>
    </div>

    <select
        id="dashPeriod"
        name="dash_period"
        class="dashboard-date-filter__native"
        tabindex="-1"
        aria-hidden="true"
    >
        <option value="today" @selected($period === 'today')>Today</option>
        <option value="date" @selected($period === 'date')>Date</option>
        <option value="range" @selected($period === 'range')>Date range</option>
    </select>

    <div
        id="dashDateField"
        class="dashboard-date-filter__field"
        style="{{ $period === 'date' ? '' : 'display:none;' }}"
    >
        <label for="dashDate">Date</label>
        <input
            type="date"
            id="dashDate"
            name="dash_date"
            value="{{ $dateFilter['date'] }}"
            max="{{ $dateFilter['maxDate'] }}"
            {{ $period === 'date' ? '' : 'disabled' }}
        >
    </div>

    <div
        id="dashRangeFields"
        class="dashboard-date-filter__range"
        style="{{ $period === 'range' ? '' : 'display:none;' }}"
    >
        <div class="dashboard-date-filter__field">
            <label for="dashFrom">From</label>
            <input
                type="date"
                id="dashFrom"
                name="dash_from"
                value="{{ $dateFilter['from'] }}"
                max="{{ $dateFilter['maxDate'] }}"
                {{ $period === 'range' ? '' : 'disabled' }}
            >
        </div>
        <div class="dashboard-date-filter__field">
            <label for="dashTo">To</label>
            <input
                type="date"
                id="dashTo"
                name="dash_to"
                value="{{ $dateFilter['to'] }}"
                max="{{ $dateFilter['maxDate'] }}"
                {{ $period === 'range' ? '' : 'disabled' }}
            >
        </div>
    </div>

    <button
        type="submit"
        class="dashboard-date-filter__apply"
        id="dashApply"
        style="{{ $period === 'today' ? 'display:none;' : '' }}"
    >
        Apply
    </button>
</form>

@error('dash_date')
    <p class="dashboard-date-filter__error">{{ $message }}</p>
@enderror
@error('dash_from')
    <p class="dashboard-date-filter__error">{{ $message }}</p>
@enderror
@error('dash_to')
    <p class="dashboard-date-filter__error">{{ $message }}</p>
@enderror
</div>

<script>
    (function () {
        const form = document.getElementById('dashboardDateFilter');
        const period = document.getElementById('dashPeriod');
        const dateField = document.getElementById('dashDateField');
        const rangeFields = document.getElementById('dashRangeFields');
        const apply = document.getElementById('dashApply');
        const dateInput = document.getElementById('dashDate');
        const fromInput = document.getElementById('dashFrom');
        const toInput = document.getElementById('dashTo');

        if (!form || !period) {
            return;
        }

        const modes = form.querySelectorAll('[data-period]');

        function syncFields() {
            const value = period.value;
            const isDate = value === 'date';
            const isRange = value === 'range';

            modes.forEach(function (button) {
                button.classList.toggle('is-on', button.getAttribute('data-period') === value);
            });
            if (dateField) {
                dateField.style.display = isDate ? '' : 'none';
            }
            if (rangeFields) {
                rangeFields.style.display = isRange ? '' : 'none';
            }
            if (apply) {
                apply.style.display = value === 'today' ? 'none' : '';
            }
            if (dateInput) {
                dateInput.disabled = !isDate;
            }
            if (fromInput) {
                fromInput.disabled = !isRange;
            }
            if (toInput) {
                toInput.disabled = !isRange;
            }
        }

        modes.forEach(function (button) {
            button.addEventListener('click', function () {
                const next = button.getAttribute('data-period');
                if (period.value === next && next !== 'today') {
                    return;
                }
                period.value = next;
                syncFields();
                if (next === 'today') {
                    form.submit();
                }
            });
        });
    })();
</script>
