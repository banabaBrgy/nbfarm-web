@php
    $change = $change ?? null;
    $hasBase = (bool) ($hasBase ?? false);
    $empty = $empty ?? '—';
@endphp
@if($hasBase && $change !== null)
    <small class="{{ (float) $change >= 0 ? 'stat-trend-up' : 'stat-trend-down' }}">
        {{ (float) $change >= 0 ? '+' : '-' }}{{ number_format(abs((float) $change), 1) }}%
    </small>
@else
    <small class="stat-trend-flat">{{ $empty }}</small>
@endif
