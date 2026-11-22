@php
    $rating = is_array($rating ?? null) ? $rating : [];
    $hasBase = (bool) ($rating['hasBase'] ?? false);
    $change = $rating['change'] ?? null;
@endphp
@if($hasBase && $change !== null)
    <span class="inventory-levels-item__trend {{ (float) $change >= 0 ? 'stat-trend-up' : 'stat-trend-down' }}">
        {{ (float) $change >= 0 ? '+' : '-' }}{{ number_format(abs((float) $change), 1) }}%
    </span>
@endif
