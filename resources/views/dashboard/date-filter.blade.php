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
    $fromLabel = \Carbon\Carbon::parse($dateFilter['from'])->format('M j, Y');
    $toLabel = \Carbon\Carbon::parse($dateFilter['to'])->format('M j, Y');
    $currentLabel = $dateFilter['from'] === $dateFilter['to']
        ? $fromLabel
        : $fromLabel.' – '.$toLabel;
    $dateFilterOpen = $errors->has('dash_date') || $errors->has('dash_from') || $errors->has('dash_to');
@endphp

<div class="dashboard-date-filter-wrap">
<form
    method="GET"
    action="{{ url()->current() }}"
    class="dashboard-date-filter{{ $dateFilterOpen ? ' is-open' : '' }}"
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

    <span class="dashboard-date-filter__current">{{ $currentLabel }}</span>
    <button
        type="button"
        class="dashboard-date-filter__toggle"
        id="dashFilterToggle"
        aria-expanded="{{ $dateFilterOpen ? 'true' : 'false' }}"
        aria-controls="dashRangeFields"
        aria-label="Choose a date range"
    >
        <i class="fas fa-filter" aria-hidden="true"></i>
    </button>

    <div class="dashboard-date-filter__range" id="dashRangeFields">
        <div class="dashboard-date-filter__field">
            <label for="dashFrom" class="dashboard-date-filter__sr">From</label>
            <span class="dashboard-date-filter__display" data-date-display>{{ $fromLabel }}</span>
            <input
                type="date"
                id="dashFrom"
                name="dash_from"
                value="{{ $dateFilter['from'] }}"
                max="{{ $dateFilter['maxDate'] }}"
                data-auto-submit
            >
        </div>
        <span class="dashboard-date-filter__sep" aria-hidden="true">-</span>
        <div class="dashboard-date-filter__field">
            <label for="dashTo" class="dashboard-date-filter__sr">To</label>
            <span class="dashboard-date-filter__display" data-date-display>{{ $toLabel }}</span>
            <input
                type="date"
                id="dashTo"
                name="dash_to"
                value="{{ $dateFilter['to'] }}"
                max="{{ $dateFilter['maxDate'] }}"
                data-auto-submit
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
        const toggle = document.getElementById('dashFilterToggle');
        const fromInput = document.getElementById('dashFrom');
        const toInput = document.getElementById('dashTo');
        const openKey = 'nbDashboardDateFilterOpen';

        if (!form || !toggle || !fromInput || !toInput) {
            return;
        }

        if (sessionStorage.getItem(openKey) === '1') {
            form.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }

        function formatDateLabel(value) {
            if (!value) {
                return '';
            }

            const parts = value.split('-').map(Number);
            if (parts.length !== 3 || parts.some(Number.isNaN)) {
                return value;
            }

            return new Date(parts[0], parts[1] - 1, parts[2]).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });
        }

        function syncDisplay(input) {
            const display = input.parentElement.querySelector('[data-date-display]');
            if (display) {
                display.textContent = formatDateLabel(input.value);
            }
        }

        toggle.addEventListener('click', function () {
            const open = form.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            sessionStorage.setItem(openKey, open ? '1' : '0');
        });

        function applyDates() {
            syncDisplay(fromInput);
            syncDisplay(toInput);

            if (!fromInput.value || !toInput.value) {
                return;
            }

            if (fromInput.value > toInput.value) {
                const nextFrom = toInput.value;
                toInput.value = fromInput.value;
                fromInput.value = nextFrom;
                syncDisplay(fromInput);
                syncDisplay(toInput);
            }
        }

        function openPicker(input) {
            if (typeof input.showPicker !== 'function') {
                return;
            }

            try {
                input.showPicker();
            } catch (error) {
                // Native click still opens the picker when showPicker is blocked.
            }
        }

        fromInput.addEventListener('input', function () {
            syncDisplay(fromInput);
        });
        toInput.addEventListener('input', function () {
            syncDisplay(toInput);
        });
        fromInput.addEventListener('change', applyDates);
        toInput.addEventListener('change', applyDates);
        fromInput.addEventListener('click', function () {
            openPicker(fromInput);
        });
        toInput.addEventListener('click', function () {
            openPicker(toInput);
        });
    })();
</script>
