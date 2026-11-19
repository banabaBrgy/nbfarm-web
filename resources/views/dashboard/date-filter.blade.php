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

    <div class="dashboard-date-filter__range">
        <div class="dashboard-date-filter__field">
            <label for="dashFrom">From</label>
            <input
                type="date"
                id="dashFrom"
                name="dash_from"
                value="{{ $dateFilter['from'] }}"
                max="{{ $dateFilter['maxDate'] }}"
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
            >
        </div>
    </div>
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
        const fromInput = document.getElementById('dashFrom');
        const toInput = document.getElementById('dashTo');

        if (!form || !fromInput || !toInput) {
            return;
        }

        function applyDates() {
            if (!fromInput.value || !toInput.value) {
                return;
            }

            if (fromInput.value > toInput.value) {
                const nextFrom = toInput.value;
                toInput.value = fromInput.value;
                fromInput.value = nextFrom;
            }

            form.submit();
        }

        fromInput.addEventListener('change', applyDates);
        toInput.addEventListener('change', applyDates);
    })();
</script>
