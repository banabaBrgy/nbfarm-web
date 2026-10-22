<?php

namespace App\Services;

use App\Models\DailyInventoryLog;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;

class DailyInventory
{
    public const TRAY_DAILY_STOCK = 500;

    public const FEED_DAILY_USE = 15;

    public const VITAMIN_DAILY_USE = 5;

    /** Once per calendar day: reset trays and deduct feeds/vitamins. */
    public function applyForToday(): void
    {
        $today = now()->toDateString();

        if (DailyInventoryLog::where('log_date', $today)->exists()) {
            $this->catchUpNewRates($today);
            $this->resetEggTrays();
            $this->syncAlerts();

            return;
        }

        $this->decrementSupply('Feeds', self::FEED_DAILY_USE, 'Daily feed use (15 sacks)');
        $this->decrementSupply('Vitamins', self::VITAMIN_DAILY_USE, 'Daily vitamin use (5 bottles)');
        $this->resetEggTrays();

        DailyInventoryLog::create([
            'log_date' => $today,
        ]);

        $this->syncAlerts();
    }

    /** Apply the new 15/5 daily rates if today's old -1 deduct already ran. */
    private function catchUpNewRates(string $today): void
    {
        $vitaminDone = InventoryItemHistory::query()
            ->where('item_name', 'Vitamins')
            ->whereDate('created_at', $today)
            ->where('note', 'like', '%vitamin use%')
            ->exists();

        if (! $vitaminDone) {
            $this->decrementSupply('Vitamins', self::VITAMIN_DAILY_USE, 'Daily vitamin use (5 bottles)');
        }

        $newFeedDone = InventoryItemHistory::query()
            ->where('item_name', 'Feeds')
            ->whereDate('created_at', $today)
            ->where('note', 'like', '%15 sacks%')
            ->exists();

        if ($newFeedDone) {
            return;
        }

        $oldFeedDeduct = InventoryItemHistory::query()
            ->where('item_name', 'Feeds')
            ->whereDate('created_at', $today)
            ->where('note', 'Daily feed use')
            ->exists();

        $qty = $oldFeedDeduct ? self::FEED_DAILY_USE - 1 : self::FEED_DAILY_USE;
        $this->decrementSupply('Feeds', $qty, 'Daily feed use (15 sacks)');
    }

    private function resetEggTrays(): void
    {
        $item = InventoryItem::where('item_name', 'Egg Trays')->first();

        if (! $item) {
            return;
        }

        // Only the first daily restock should run; later page loads must keep
        // restocks and harvest deductions for the day.
        $alreadyReset = InventoryItemHistory::query()
            ->where('inventory_item_id', $item->id)
            ->whereDate('created_at', today())
            ->where('note', 'Daily tray restock')
            ->exists();

        if ($alreadyReset) {
            return;
        }

        $before = (float) $item->current_stock;
        $after = (float) ($item->daily_stock ?? self::TRAY_DAILY_STOCK);

        if ($before !== $after) {
            $item->update(['current_stock' => $after]);
            $item->current_stock = $after;

            Inventory::where('item_type', 'Egg Tray')->update([
                'quantity' => (int) $after,
            ]);
        }

        // Always latch the daily restock, even when stock already matches,
        // so later page loads keep harvest deductions and manual restocks.
        InventoryItemHistory::record(
            $item->id,
            $item->item_name,
            $before === $after ? 'Checked' : 'Reset',
            $after - $before,
            $before,
            $after,
            'Daily tray restock'
        );

        if ($before !== $after) {
            app(LowStockNotifier::class)->check($item);
        }
    }

    private function decrementSupply(string $itemName, float $qty, string $note): void
    {
        if ($qty <= 0) {
            return;
        }

        $item = InventoryItem::where('item_name', $itemName)->first();

        if (! $item) {
            return;
        }

        $before = (float) $item->current_stock;
        $after = max(0, $before - $qty);
        $item->update(['current_stock' => $after]);
        $item->current_stock = $after;

        InventoryItemHistory::record(
            $item->id,
            $item->item_name,
            'Deducted',
            $after - $before,
            $before,
            $after,
            $note
        );

        app(LowStockNotifier::class)->check($item);
    }

    private function syncAlerts(): void
    {
        InventoryItem::query()->each(
            fn (InventoryItem $item) => app(LowStockNotifier::class)->check($item)
        );
    }
}
