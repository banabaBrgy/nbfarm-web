<?php

namespace App\Services;

use App\Models\DailyInventoryLog;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;

class DailyInventory
{
    public const TRAY_DAILY_STOCK = 500;

    /** Once per calendar day: reset trays to daily baseline and deduct feeds/supplements. */
    public function applyForToday(): void
    {
        $today = now()->toDateString();

        if (DailyInventoryLog::where('log_date', $today)->exists()) {
            return;
        }

        $this->decrementSupply('Feeds', 'Daily feed use');
        $this->decrementSupply('Supplement', 'Daily supplement use');
        $this->decrementSupply('Supplements', 'Daily supplement use');
        $this->resetEggTrays();

        DailyInventoryLog::create([
            'log_date' => $today,
        ]);
    }

    private function resetEggTrays(): void
    {
        $item = InventoryItem::where('item_name', 'Egg Trays')->first();

        if (! $item) {
            return;
        }

        $before = (float) $item->current_stock;
        $after = (float) ($item->daily_stock ?? self::TRAY_DAILY_STOCK);

        if ($before === $after) {
            return;
        }

        $item->update(['current_stock' => $after]);

        Inventory::where('item_type', 'Egg Tray')->update([
            'quantity' => (int) $after,
        ]);

        InventoryItemHistory::record(
            $item->id,
            $item->item_name,
            'Reset',
            $after - $before,
            $before,
            $after,
            'Daily tray restock'
        );
    }

    private function decrementSupply(string $itemName, string $note): void
    {
        $item = InventoryItem::where('item_name', $itemName)->first();

        if (! $item) {
            return;
        }

        $before = (float) $item->current_stock;
        $after = max(0, $before - 1);
        $item->update(['current_stock' => $after]);

        InventoryItemHistory::record(
            $item->id,
            $item->item_name,
            'Deducted',
            -1,
            $before,
            $after,
            $note
        );
    }
}
