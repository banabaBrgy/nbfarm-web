@extends('layouts.sidebar')

@section('title', 'Inventory')
@section('page-label', 'Inventory')

@push('styles')
  <link rel="stylesheet" href="{{ versioned_asset('css/inventory.css') }}">
@endpush

@section('content')
@php
    // Client: staff can restock existing items (row Edit), but not add brand-new items (top Add Stock).
    $canAddNewItem = false;
    $canRestockInventory = true;
    $canDeleteInventory = \App\Models\User::routeRole() === 'owner';
@endphp
<div class="inventory-page">

    <div class="page-header">
        <div>
            <h2>Inventory</h2>
            <p>Monitor stock levels, alerts, and recent updates.</p>
        </div>
        <div class="page-header-actions">
            <button class="btn-forecast" type="button" onclick="openInventoryForecast()">
                <i class="fas fa-chart-line"></i>
                Inventory Forecast
            </button>
        </div>
    </div>

   @php
    $alertItems = $items->filter(fn($item) => in_array($item->status, ['LOW', 'WATCH']));
@endphp

@if($alertItems->isNotEmpty())
    @foreach($alertItems as $alertItem)
        <div class="alert-low {{ $alertItem->status === 'WATCH' ? 'alert-watch' : '' }}">
            @if($alertItem->status === 'LOW')
                ⚠ <strong>{{ $alertItem->item_name }} stock below reorder point</strong>
            @else
                👁 <strong>{{ $alertItem->item_name }} stock approaching minimum level</strong>
            @endif
            — Current: {{ number_format($alertItem->current_stock, 2) }} {{ $alertItem->unit }} | Minimum: {{ number_format($alertItem->min_level, 2) }} {{ $alertItem->unit }}.
            {{ $alertItem->status === 'LOW' ? 'Immediate reorder suggested.' : 'Monitor closely.' }}
        </div>
    @endforeach
@endif

    <div class="stats-grid">
        <div class="stat-card stat-card--eggs">
            <div class="stat-icon"><i class="fas fa-egg"></i></div>
            <div class="stat-info">
                <span class="stat-title">Eggs Stock</span>
                <h2>{{ number_format($eggsStock) }}</h2>
                <small>Today: {{ number_format($eggsToday ?? 0) }} · Yesterday: {{ number_format($eggsYesterday ?? 0) }}</small>
            </div>
        </div>

        <div class="stat-card stat-card--feed {{ ($feed?->status ?? '') === 'LOW' ? 'stat-card-alert' : (($feed?->status ?? '') === 'WATCH' ? 'stat-card-watch' : '') }}">
            <div class="stat-icon"><i class="fas fa-wheat-awn"></i></div>
            <div class="stat-info">
                <span class="stat-title">Feed</span>
                <h2 class="{{ ($feed?->status ?? '') === 'LOW' ? 'text-danger' : (($feed?->status ?? '') === 'WATCH' ? 'text-watch' : '') }}">
                    {{ number_format($feed?->current_stock ?? 0, 2) }}
                </h2>
                <small>{{ $feed?->unit ?? 'sacks' }} — Min: {{ number_format($feed?->min_level ?? 0, 2) }} — {{ $feed?->status ?? 'OK' }}</small>
            </div>
        </div>

        <div class="stat-card stat-card--vitamins {{ ($vitamins?->status ?? '') === 'LOW' ? 'stat-card-alert' : (($vitamins?->status ?? '') === 'WATCH' ? 'stat-card-watch' : '') }}">
            <div class="stat-icon"><i class="fas fa-capsules"></i></div>
            <div class="stat-info">
                <span class="stat-title">Vitamins</span>
                <h2 class="{{ ($vitamins?->status ?? '') === 'LOW' ? 'text-danger' : (($vitamins?->status ?? '') === 'WATCH' ? 'text-watch' : '') }}">
                    {{ number_format($vitamins?->current_stock ?? 0) }}
                </h2>
                <small>{{ $vitamins?->unit ?? 'bottles' }} — Min: {{ number_format($vitamins?->min_level ?? 0, 2) }} — {{ $vitamins?->status ?? 'OK' }}</small>
            </div>
        </div>

        <div class="stat-card stat-card--inventory {{ ($eggTrays?->status ?? '') === 'LOW' ? 'stat-card-alert' : (($eggTrays?->status ?? '') === 'WATCH' ? 'stat-card-watch' : '') }}">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-info">
                <span class="stat-title">Egg Trays</span>
                <h2 class="{{ ($eggTrays?->status ?? '') === 'LOW' ? 'text-danger' : (($eggTrays?->status ?? '') === 'WATCH' ? 'text-watch' : '') }}">
                    {{ number_format($eggTraysDisplay ?? 0) }}
                </h2>
                <small>{{ $eggTrays?->unit ?? 'pcs' }} — Min: {{ number_format($eggTrays?->min_level ?? 0, 2) }} — {{ $eggTrays?->status ?? 'OK' }}</small>
            </div>
        </div>
    </div>

    @include('components.inventory-forecast')

<!-- FILTER BAR (aligned right) -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search item..." value="{{ $search }}">

        <select name="status" data-auto-submit>
            <option value="">All Status</option>
            <option value="LOW" {{ $statusFilter === 'LOW' ? 'selected' : '' }}>Low</option>
            <option value="WATCH" {{ $statusFilter === 'WATCH' ? 'selected' : '' }}>Watch</option>
            <option value="OK" {{ $statusFilter === 'OK' ? 'selected' : '' }}>OK</option>
        </select>
    </form>

    <!-- ITEMS TABLE -->
    <div class="inventory-table-wrapper">
        <table class="inventory-table has-action-column">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Current Stock</th>
                    <th>Min Level</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
    <tbody>
    @forelse($items as $item)
    <tr>
        <td>{{ $item->item_name }} ({{ $item->unit }})</td>
        <td class="{{ $item->status === 'LOW' ? 'text-danger fw-bold' : '' }}">
            {{ $item->current_stock }}
        </td>
        <td>{{ $item->min_level }}</td>
        <td>
            <span class="status-icon status-icon-{{ strtolower($item->status) }}">
                @if($item->status === 'OK')
                    <i class="fas fa-check-circle"></i>
                @elseif($item->status === 'WATCH')
                    <i class="fas fa-eye"></i>
                @else
                    <i class="fas fa-exclamation-triangle"></i>
                @endif
                {{ $item->status }}
            </span>
        </td>
        <td class="action-cell">
    @if($canRestockInventory)
        <button type="button" class="icon-btn icon-btn-edit" onclick="openIncreaseModal({{ $item->id }}, @js($item->item_name))" title="Edit / Add Stock">
            <i class="fas fa-pen"></i>
        </button>
    @endif
    @if($canDeleteInventory)
        <form action="{{ route('owner.inventory.delete', $item->id) }}" method="POST" style="display:inline;" class="delete-item-form">
            @csrf
            @method('DELETE')
            <button type="submit" class="icon-btn icon-btn-delete" title="Delete" data-item-name="{{ $item->item_name }}">
                <i class="fas fa-trash"></i>
            </button>
        </form>
    @endif
    <button type="button" class="icon-btn icon-btn-history" onclick="openHistoryModal({{ $item->id }})" title="History">
        <i class="fas fa-clock"></i>
    </button>
</td>
    </tr>
    @empty
    <tr><td colspan="5" style="text-align:center; color:#999; padding:20px;">No items found.</td></tr>
    @endforelse
</tbody>
        </table>
    </div>

    @if($items->hasPages())
        <div class="pagination-bar">
            {{ $items->links() }}
        </div>
    @endif

    <!-- TWO COLUMN: EGG TOTALS + RECENT MOVEMENTS -->
    <div class="bottom-grid">

        <!-- EGG PRODUCTION SUMMARY (per size, hindi per batch) -->
        <div class="inventory-table-wrapper">
            <div class="egg-summary-filter" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h2 class="section-title" style="margin:0;">Egg Production Summary</h2>

                <form method="GET" style="display:flex; gap:8px; align-items:center;">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="status" value="{{ $statusFilter }}">

                    <select name="year" class="sort-select" data-auto-submit>
                        @foreach(($availableYears ?? $availableMonths->pluck('year')->unique()->sortDesc()) as $year)
                            <option value="{{ $year }}" {{ (int) $year === (int) $selectedYear ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>

                    <select name="month" class="sort-select" data-auto-submit>
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ (int) $m === (int) $selectedMonth ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>

            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Egg Size</th>
                        <th>Total Produced</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $sizes = [
                            'Small' => $eggTotals->small ?? 0,
                            'Medium' => $eggTotals->medium ?? 0,
                            'Large' => $eggTotals->large ?? 0,
                            'Extra Large' => $eggTotals->extra_large ?? 0,
                            'Cracked' => $eggTotals->cracked ?? 0,
                        ];
                    @endphp

                    @foreach($sizes as $label => $value)
                    <tr>
                        <td>{{ $label }}</td>
                        <td><strong>{{ number_format($value) }}</strong></td>
                        <td>
                            <div class="mini-bar">
                                <div class="mini-bar-fill" style="width: {{ $grandTotal > 0 ? round(($value / $grandTotal) * 100, 1) : 0 }}%"></div>
                            </div>
                            <span class="mini-bar-label">{{ $grandTotal > 0 ? round(($value / $grandTotal) * 100, 1) : 0 }}%</span>
                        </td>
                    </tr>
                    @endforeach

                    <tr class="table-total-row">
                        <td><strong>TOTAL</strong></td>
                        <td><strong>{{ number_format($grandTotal) }}</strong></td>
                        <td>100%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- RECENT STOCK MOVEMENTS -->
        <div class="inventory-table-wrapper">
            <h2 class="section-title" style="margin-top:0;">Recent Stock Updates</h2>

            <ul class="movement-list">
                @forelse($recentMovements as $move)
                <li class="movement-item">
                    <div class="movement-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="movement-info">
                        <strong>{{ $move->item_name }}</strong>
                        <span class="movement-sub">Current stock: {{ $move->current_stock }} {{ $move->unit }}</span>
                    </div>
                    <div class="movement-time">
                        {{ \Carbon\Carbon::parse($move->updated_at)->diffForHumans() }}
                    </div>
                </li>
                @empty
                <li class="movement-item" style="justify-content:center; color:#999;">No recent updates.</li>
                @endforelse
            </ul>
        </div>

    </div>

</div>

@if($canAddNewItem)
<!-- ADD NEW ITEM MODAL -->
<div class="modal-overlay" id="stockModal">
    <div class="modal-box">
        <h3>Add New Item</h3>
        <form action="{{ route(\App\Models\User::routeRole() . '.inventory.addStock') }}" method="POST">
    @csrf

            <label>Item Name</label>
            <input type="text" name="item_name" placeholder="e.g. Feed" required>

            <label>Unit</label>
            <input type="text" name="unit" placeholder="e.g. sacks, pcs, bottles" required>

            <label>Current Stock</label>
            <input type="number" name="current_stock" step="0.01" min="0" required>

            <label>Minimum Stock (Reorder Level)</label>
            <input type="number" name="min_level" step="0.01" min="0" required>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeStockModal()">Cancel</button>
                <button type="submit" class="btn-save">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($canRestockInventory)
<!-- ADD STOCK QUANTITY MODAL -->
<div class="modal-overlay" id="increaseModal">
    <div class="modal-box">
        <h3>Add Stock Quantity</h3>
        <p style="color:#777; font-size:13px; margin:-10px 0 16px;">Item: <strong id="increaseItemName"></strong></p>
        <form action="{{ route(\App\Models\User::routeRole() . '.inventory.increaseStock') }}" method="POST">
    @csrf
  <input type="hidden" name="item_id" id="increaseItemId">
         
            

            <label>Quantity to Add</label>
            <input type="number" name="quantity" step="0.01" min="0.01" required>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeIncreaseModal()">Cancel</button>
                <button type="submit" class="btn-save">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- ITEM HISTORY MODAL -->
<div class="modal-overlay" id="historyModal">
    <div class="modal-box history-modal-box">
        <h3>Stock History</h3>
        <p style="color:#777; font-size:13px; margin:-10px 0 16px;">Item: <strong id="historyItemName"></strong></p>
        <div class="history-table-wrap">
            <table class="inventory-table history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Change</th>
                        <th>Stock after</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <tr><td colspan="4" style="text-align:center; color:#999;">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeHistoryModal()">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

@if($canAddNewItem)
function openStockModal() {
    document.getElementById('stockModal').classList.add('active');
}

function closeStockModal() {
    document.getElementById('stockModal').classList.remove('active');
}
@endif

@if($canRestockInventory)
function openIncreaseModal(itemId, itemName) {
    document.getElementById('increaseItemId').value = itemId;
    document.getElementById('increaseItemName').textContent = itemName;
    document.getElementById('increaseModal').classList.add('active');
}

function closeIncreaseModal() {
    document.getElementById('increaseModal').classList.remove('active');
}
@endif

const historyUrlTemplate = @json(route(\App\Models\User::routeRole() . '.inventory.history', ['id' => 999999]));

function openHistoryModal(itemId) {
    const modal = document.getElementById('historyModal');
    const body = document.getElementById('historyTableBody');
    document.getElementById('historyItemName').textContent = '…';
    body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999;">Loading…</td></tr>';
    modal.classList.add('active');

    fetch(historyUrlTemplate.replace('999999', itemId), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(res => {
            if (!res.ok) throw new Error('Could not load history');
            return res.json();
        })
        .then(data => {
            document.getElementById('historyItemName').textContent = data.item + ' (' + data.unit + ')';
            if (!data.history.length) {
                body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999; padding:20px;">No history yet.</td></tr>';
                return;
            }
            body.innerHTML = data.history.map(row => {
                const change = Number(row.change);
                const sign = change > 0 ? '+' : '';
                return '<tr>' +
                    '<td>' + row.when + '</td>' +
                    '<td>' + row.action + (row.note ? '<div class="history-note">' + row.note + '</div>' : '') + '</td>' +
                    '<td>' + sign + Number(change).toLocaleString() + '</td>' +
                    '<td>' + Number(row.after).toLocaleString() + '</td>' +
                    '</tr>';
            }).join('');
        })
        .catch(() => {
            body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#c0392b;">Could not load history.</td></tr>';
        });
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.remove('active');
}

@if($canAddNewItem)
document.getElementById('stockModal').addEventListener('click', function(e) {
    if (e.target === this) closeStockModal();
});
@endif

@if($canRestockInventory)
document.getElementById('increaseModal').addEventListener('click', function(e) {
    if (e.target === this) closeIncreaseModal();
});
@endif

document.getElementById('historyModal').addEventListener('click', function(e) {
    if (e.target === this) closeHistoryModal();
});

document.querySelectorAll('.delete-item-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const itemName = this.querySelector('button').dataset.itemName;

        Swal.fire({
            title: 'Delete ' + itemName + '?',
            text: 'This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#999',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });
});

@if(session('success'))
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: @json(session('success')),
    confirmButtonColor: '#3B8F47',
    timer: 2500,
    timerProgressBar: true,
});
@endif
</script>
@endpush