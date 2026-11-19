<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use Carbon\Carbon;

class HistoricalInventory
{
    public function namedStock(string $itemName, Carbon $asOf, bool $useCurrent): float
    {
        if ($useCurrent) {
            return (float) InventoryItem::query()
                ->where('item_name', $itemName)
                ->sum('current_stock');
        }

        $total = 0.0;
        foreach (InventoryItem::query()->where('item_name', $itemName)->get() as $item) {
            $total += $this->stockForItem($item, $asOf) ?? 0.0;
        }

        return $total;
    }

    public function firstNamedStock(string $itemName, Carbon $asOf, bool $useCurrent): float
    {
        $item = InventoryItem::query()->where('item_name', $itemName)->first();

        if (! $item) {
            return 0.0;
        }

        if ($useCurrent) {
            return (float) ($item->current_stock ?? 0);
        }

        return $this->stockForItem($item, $asOf) ?? 0.0;
    }

    public function namedMin(string $itemName): float
    {
        return (float) InventoryItem::query()
            ->where('item_name', $itemName)
            ->sum('min_level');
    }

    public function lowStockCount(Carbon $asOf, bool $useCurrent): int
    {
        if ($useCurrent) {
            return InventoryItem::query()
                ->whereColumn('current_stock', '<=', 'min_level')
                ->count();
        }

        $count = 0;
        foreach (InventoryItem::query()->get() as $item) {
            $stock = $this->stockForItem($item, $asOf);
            if ($stock === null) {
                continue;
            }

            if ($stock <= (float) $item->min_level) {
                $count++;
            }
        }

        return $count;
    }

    public function stockForItem(InventoryItem $item, Carbon $asOf): ?float
    {
        $row = InventoryItemHistory::query()
            ->where('inventory_item_id', $item->id)
            ->where('created_at', '<=', $asOf->copy()->endOfDay())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $row || $row->stock_after === null) {
            return null;
        }

        return (float) $row->stock_after;
    }
}
