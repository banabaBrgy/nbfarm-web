<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

    /**
     * Stock added and removed for each item during the selected dates.
     *
     * @return list<array{name: string, unit: string, stockIn: float, stockOut: float}>
     */
    public function movements(Carbon $start, Carbon $end): array
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->endOfDay();
        $items = InventoryItem::query()->orderBy('item_name')->get();

        if ($items->isEmpty()) {
            return [$this->eggMovement($start, $end)];
        }

        $changes = InventoryItemHistory::query()
            ->whereIn('inventory_item_id', $items->pluck('id'))
            ->whereBetween('created_at', [$from, $to])
            ->get(['inventory_item_id', 'quantity_change'])
            ->groupBy('inventory_item_id');

        $rows = [$this->eggMovement($start, $end)];
        foreach ($items as $item) {
            $stockIn = 0.0;
            $stockOut = 0.0;

            foreach ($changes->get($item->id, []) as $row) {
                $change = (float) $row->quantity_change;
                if ($change > 0) {
                    $stockIn += $change;
                } elseif ($change < 0) {
                    $stockOut += abs($change);
                }
            }

            $rows[] = [
                'name' => $item->item_name,
                'unit' => (string) ($item->unit ?? ''),
                'stockIn' => $stockIn,
                'stockOut' => $stockOut,
            ];
        }

        return $this->orderedRows($rows);
    }

    /**
     * @return array{name: string, unit: string, stockIn: float, stockOut: float}
     */
    private function eggMovement(Carbon $start, Carbon $end): array
    {
        $produced = (int) Production::query()
            ->whereDate('production_date', '>=', $start->toDateString())
            ->whereDate('production_date', '<=', $end->toDateString())
            ->sum('eggs_produced');

        $trays = (float) DB::table('dispatch_items')
            ->join('dispatches', 'dispatches.id', '=', 'dispatch_items.dispatch_id')
            ->whereDate('dispatches.dispatch_date', '>=', $start->toDateString())
            ->whereDate('dispatches.dispatch_date', '<=', $end->toDateString())
            ->sum('dispatch_items.quantity');

        return [
            'name' => 'Eggs',
            'unit' => 'pcs',
            'stockIn' => (float) $produced,
            'stockOut' => $trays * Production::EGGS_PER_TRAY,
        ];
    }

    /**
     * @param list<array{name: string, unit: string, stockIn: float, stockOut: float}> $rows
     * @return list<array{name: string, unit: string, stockIn: float, stockOut: float}>
     */
    private function orderedRows(array $rows): array
    {
        $rank = [
            'Eggs' => 0,
            'Feeds' => 1,
            'Feed' => 1,
            'Egg Trays' => 2,
        ];

        usort($rows, function (array $left, array $right) use ($rank): int {
            $leftRank = $rank[$left['name']] ?? 10;
            $rightRank = $rank[$right['name']] ?? 10;

            if ($leftRank === $rightRank) {
                return strcasecmp($left['name'], $right['name']);
            }

            return $leftRank <=> $rightRank;
        });

        return array_values($rows);
    }
}
