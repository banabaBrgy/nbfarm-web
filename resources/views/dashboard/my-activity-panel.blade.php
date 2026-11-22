@php
    $staffActivities = $staffActivities ?? [
        'items' => [],
        'empty' => true,
        'subtitle' => 'Your recent activities in the system',
        'emptyMessage' => 'No production or inventory activity yet today.',
        'emptyMessages' => [
            'all' => 'No production or inventory activity yet today.',
            'production' => 'No production activity yet today.',
            'inventory' => 'No inventory activity yet today.',
        ],
    ];
    $activityItems = $staffActivities['items'] ?? [];
    $emptyMessages = $staffActivities['emptyMessages'] ?? [
        'all' => $staffActivities['emptyMessage'],
        'production' => 'No production activity yet today.',
        'inventory' => 'No inventory activity yet today.',
    ];
@endphp

<div
    class="panel-card my-activity-card"
    data-activity-panel
    data-empty-messages="{{ json_encode($emptyMessages, JSON_UNESCAPED_UNICODE) }}"
>
    <div class="my-activity-head">
        <span class="my-activity-head-icon" aria-hidden="true">
            <i class="fas fa-clipboard-list"></i>
        </span>
        <div>
            <h3 class="my-activity-title">My Activity</h3>
            @if(! empty($staffActivities['subtitle']))
                <p class="my-activity-subtitle">{{ $staffActivities['subtitle'] }}</p>
            @endif
        </div>
    </div>

    <div class="my-activity-filters" role="group" aria-label="Filter activities">
        <button type="button" class="my-activity-filter is-active" data-activity-filter="all" aria-pressed="true">
            <i class="fas fa-list-ul" aria-hidden="true"></i>
            All Activities
        </button>
        <button type="button" class="my-activity-filter" data-activity-filter="production" aria-pressed="false">
            <i class="fas fa-egg" aria-hidden="true"></i>
            Production
        </button>
        <button type="button" class="my-activity-filter" data-activity-filter="inventory" aria-pressed="false">
            <i class="fas fa-box" aria-hidden="true"></i>
            Inventory
        </button>
    </div>

    <p
        class="my-activity-empty"
        role="status"
        data-activity-empty
        @if($activityItems !== []) hidden @endif
    >{{ $emptyMessages['all'] }}</p>

    @if($activityItems !== [])
        <ul class="my-activity-list">
            @foreach($activityItems as $activity)
                <li
                    class="my-activity-item my-activity-item--{{ $activity['type'] }}"
                    data-activity-group="{{ $activity['group'] ?? $activity['type'] }}"
                >
                    <a href="{{ $activity['url'] }}" class="my-activity-item__link">
                        <span class="my-activity-item__icon" aria-hidden="true">
                            <i class="{{ $activity['icon'] }}"></i>
                        </span>
                        <span class="my-activity-item__body">
                            <strong>{{ $activity['title'] }}</strong>
                            <small>{{ $activity['detail'] }}</small>
                        </span>
                        <time class="my-activity-item__when" datetime="{{ $activity['at'] }}">{{ $activity['when'] }}</time>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>

@once
    @push('scripts')
        <script>
            (function () {
                var root = document.querySelector('[data-activity-panel]');
                if (!root) return;

                var buttons = root.querySelectorAll('[data-activity-filter]');
                var items = root.querySelectorAll('[data-activity-group]');
                var empty = root.querySelector('[data-activity-empty]');
                var messages = {};

                try {
                    messages = JSON.parse(root.getAttribute('data-empty-messages') || '{}');
                } catch (e) {
                    messages = {};
                }

                function applyFilter(filter) {
                    var visible = 0;

                    buttons.forEach(function (btn) {
                        var on = btn.getAttribute('data-activity-filter') === filter;
                        btn.classList.toggle('is-active', on);
                        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });

                    items.forEach(function (item) {
                        var show = filter === 'all' || item.getAttribute('data-activity-group') === filter;
                        item.hidden = !show;
                        if (show) visible += 1;
                    });

                    if (empty) {
                        empty.hidden = visible > 0;
                        empty.textContent = messages[filter] || messages.all || empty.textContent;
                    }
                }

                buttons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        applyFilter(btn.getAttribute('data-activity-filter'));
                    });
                });
            })();
        </script>
    @endpush
@endonce
