@php
    $range = $dateFilters['range'] ?? 'all';
    $periodLabel = $dateFilters['label'] ?? 'All inventory records';
    $recordCount = (int) ($dateFilters['count'] ?? $recentMovements->count());
@endphp
<div class="inventory-table-wrapper">
    <div class="stock-records-header">
        <div>
            <h2 class="section-title" style="margin-top:0; margin-bottom:4px;">Inventory Records</h2>
            <span class="stock-records-period">{{ $periodLabel }}</span>
        </div>
        <span class="stock-records-count">
            {{ number_format($recordCount) }}
            {{ $recordCount === 1 ? 'record' : 'records' }}
        </span>
    </div>

    <ul class="movement-list">
        @forelse($recentMovements as $move)
            @php
                $change = (float) $move->quantity_change;
                $sign = $change > 0 ? '+' : '';
            @endphp
            <li class="movement-item">
                <div class="movement-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="movement-info">
                    <strong>{{ $move->item_name }}</strong>
                    <span class="movement-sub">
                        {{ $move->action }}
                        · {{ $sign }}{{ number_format($change, 2) }}
                        @if($move->note)
                            · {{ $move->note }}
                        @endif
                    </span>
                </div>
                <div class="movement-time">
                    {{ $move->created_at->format('M j, Y g:i A') }}
                </div>
            </li>
        @empty
            <li class="movement-item" style="justify-content:center; color:#999;">
                @if($range === 'custom' && empty($dateFilters['from']))
                    Choose a start and end date to view records.
                @else
                    No inventory records for this period.
                @endif
            </li>
        @endforelse
    </ul>
</div>
