@php
    $eggsTotal = $eggsTotal ?? 0;
    $feedStock = $feedStock ?? 0;
    $feedMin = $feedMin ?? 0;
    $feedLow = $feedLow ?? false;
    $eggTrays = $eggTrays ?? 0;
    $vitamins = $vitamins ?? 0;
    $vitaminMin = $vitaminMin ?? 0;
    $vitaminLow = $vitaminLow ?? false;

    $eggsPct = min(100, ($eggsTotal / 150) * 100);
    $feedPct = min(100, ($feedStock / max($feedMin, 1)) * 50);
    $traysPct = min(100, ($eggTrays / 5) * 100);
    $vitaminsPct = min(100, ($vitamins / max($vitaminMin, 1)) * 50);
@endphp

<div class="panel-card inventory-levels-card">
    <div class="inventory-levels-head">
        <span class="inventory-levels-head-icon" aria-hidden="true">
            <i class="fas fa-box-open"></i>
        </span>
        <h3 class="inventory-levels-title">Inventory Levels</h3>
    </div>

    <div class="inventory-levels-list">
        <div class="inventory-levels-item">
            <div class="inventory-levels-item__icon inventory-levels-item__icon--eggs" aria-hidden="true">
                <i class="fas fa-egg"></i>
            </div>
            <div class="inventory-levels-item__body">
                <div class="inventory-levels-item__meta">
                    <span class="inventory-levels-item__name inventory-levels-item__name--accent">Eggs</span>
                    <span class="inventory-levels-item__value inventory-levels-item__value--accent">{{ number_format($eggsTotal) }} pcs</span>
                </div>
                <div class="inventory-levels-bar">
                    <div class="inventory-levels-bar__fill inventory-levels-bar__fill--green" style="width: {{ $eggsPct }}%"></div>
                </div>
            </div>
        </div>

        <div class="inventory-levels-item">
            <div class="inventory-levels-item__icon inventory-levels-item__icon--feed" aria-hidden="true">
                <i class="fas fa-wheat-awn"></i>
            </div>
            <div class="inventory-levels-item__body">
                <div class="inventory-levels-item__meta">
                    <span class="inventory-levels-item__name">Feed (sack)</span>
                    <span class="inventory-levels-item__value {{ $feedLow ? 'inventory-levels-item__value--warn' : '' }}">
                        {{ number_format((float) $feedStock, 2) }}
                        @if($feedLow)
                            — below min
                        @endif
                    </span>
                </div>
                <div class="inventory-levels-bar">
                    <div class="inventory-levels-bar__fill {{ $feedLow ? 'inventory-levels-bar__fill--low' : 'inventory-levels-bar__fill--green' }}" style="width: {{ $feedPct }}%"></div>
                </div>
            </div>
        </div>

        <div class="inventory-levels-item">
            <div class="inventory-levels-item__icon inventory-levels-item__icon--trays" aria-hidden="true">
                <i class="fas fa-table-cells"></i>
            </div>
            <div class="inventory-levels-item__body">
                <div class="inventory-levels-item__meta">
                    <span class="inventory-levels-item__name">Egg Trays</span>
                    <span class="inventory-levels-item__value">{{ number_format($eggTrays) }} pcs</span>
                </div>
                <div class="inventory-levels-bar">
                    <div class="inventory-levels-bar__fill inventory-levels-bar__fill--green" style="width: {{ $traysPct }}%"></div>
                </div>
            </div>
        </div>

        <div class="inventory-levels-item">
            <div class="inventory-levels-item__icon inventory-levels-item__icon--vitamins" aria-hidden="true">
                <i class="fas fa-pills"></i>
            </div>
            <div class="inventory-levels-item__body">
                <div class="inventory-levels-item__meta">
                    <span class="inventory-levels-item__name inventory-levels-item__name--teal">Vitamins</span>
                    <span class="inventory-levels-item__value {{ $vitaminLow ? 'inventory-levels-item__value--warn' : '' }}">
                        {{ number_format($vitamins) }} bottles
                        @if($vitaminLow)
                            — below min
                        @endif
                    </span>
                </div>
                <div class="inventory-levels-bar">
                    <div class="inventory-levels-bar__fill {{ $vitaminLow ? 'inventory-levels-bar__fill--low' : 'inventory-levels-bar__fill--teal' }}" style="width: {{ $vitaminsPct }}%"></div>
                </div>
            </div>
        </div>
    </div>
</div>
