@php
    $rating = is_array($rating ?? null) ? $rating : [];
    $hasBase = (bool) ($rating['hasBase'] ?? false);
    $change = $rating['change'] ?? null;
    $empty = $rating['empty'] ?? 'No previous data';

    $tone = 'flat';
    $arrow = null;
    if ($hasBase && $change !== null) {
        $value = (float) $change;
        if ($value > 0) {
            $tone = 'up';
            $arrow = 'up';
        } elseif ($value < 0) {
            $tone = 'down';
            $arrow = 'down';
        }
    }
@endphp
@if($hasBase && $change !== null)
    <small class="target-rating target-rating--{{ $tone }}">
        @if($arrow === 'up')
            <i class="fas fa-arrow-up" aria-hidden="true"></i>
        @elseif($arrow === 'down')
            <i class="fas fa-arrow-down" aria-hidden="true"></i>
        @endif
        <span>{{ (float) $change > 0 ? '+' : '' }}{{ number_format((float) $change, 1) }}%</span>
    </small>
@else
    <small class="target-rating target-rating--flat">{{ $empty }}</small>
@endif
