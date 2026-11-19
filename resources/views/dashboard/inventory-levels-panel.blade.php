@php
    $inventoryMovements = $inventoryMovements ?? [];
    $inventoryAsOfLabel = $inventoryAsOfLabel ?? null;
@endphp

<div class="panel-card inventory-levels-card">
    <div class="inventory-levels-head">
        <span class="inventory-levels-head-icon" aria-hidden="true">
            <i class="fas fa-box-open"></i>
        </span>
        <div>
            <h3 class="inventory-levels-title">Inventory</h3>
            @if($inventoryAsOfLabel)
                <p class="inventory-levels-asof">{{ $inventoryAsOfLabel }}</p>
            @endif
        </div>
    </div>

    <div class="inventory-movement-wrap">
        <table class="inventory-movement">
            <thead>
                <tr>
                    <th scope="col">Item name</th>
                    <th scope="col">Stock In</th>
                    <th scope="col">Stock Out</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inventoryMovements as $row)
                    <tr>
                        <td>
                            {{ $row['name'] }}
                            @if(! empty($row['unit']))
                                <span class="inventory-movement__unit">{{ $row['unit'] }}</span>
                            @endif
                        </td>
                        <td>{{ number_format((float) $row['stockIn'], 2) }}</td>
                        <td>{{ number_format((float) $row['stockOut'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="inventory-movement__empty">No inventory items yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
