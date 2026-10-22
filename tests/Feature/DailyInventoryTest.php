<?php

namespace Tests\Feature;

use App\Models\DailyInventoryLog;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Services\DailyInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_inventory_updates_feed_vitamins_and_trays(): void
    {
        $feed = $this->item('Feeds', 100);
        $vitamins = $this->item('Vitamins', 60);
        $trays = $this->item('Egg Trays', 450, 500);

        app(DailyInventory::class)->applyForToday();

        $this->assertSame(85.0, (float) $feed->fresh()->current_stock);
        $this->assertSame(55.0, (float) $vitamins->fresh()->current_stock);
        $this->assertSame(500.0, (float) $trays->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_item_histories', [
            'item_name' => 'Feeds',
            'action' => 'Deducted',
        ]);
        $this->assertDatabaseHas('inventory_item_histories', [
            'item_name' => 'Vitamins',
            'action' => 'Deducted',
        ]);
        $this->assertDatabaseHas('inventory_item_histories', [
            'item_name' => 'Egg Trays',
            'action' => 'Reset',
        ]);
    }

    public function test_existing_daily_log_still_runs_first_tray_restock_once(): void
    {
        $feed = $this->item('Feeds', 100);
        $vitamins = $this->item('Vitamins', 60);
        $trays = $this->item('Egg Trays', 450, 500);

        DailyInventoryLog::create(['log_date' => today()->toDateString()]);
        InventoryItemHistory::record(
            $vitamins->id,
            'Vitamins',
            'Deducted',
            -5,
            65,
            60,
            'Daily vitamin use (5 bottles)'
        );

        app(DailyInventory::class)->applyForToday();
        app(DailyInventory::class)->applyForToday();

        $this->assertSame(85.0, (float) $feed->fresh()->current_stock);
        $this->assertSame(60.0, (float) $vitamins->fresh()->current_stock);
        $this->assertSame(500.0, (float) $trays->fresh()->current_stock);
        $this->assertSame(
            1,
            InventoryItemHistory::query()
                ->where('item_name', 'Egg Trays')
                ->where('note', 'Daily tray restock')
                ->count()
        );
    }

    public function test_later_page_loads_keep_tray_restocks_after_daily_reset(): void
    {
        $trays = $this->item('Egg Trays', 450, 500);

        app(DailyInventory::class)->applyForToday();
        $this->assertSame(500.0, (float) $trays->fresh()->current_stock);

        $trays->update([
            'current_stock' => 800,
            'daily_stock' => 800,
        ]);

        app(DailyInventory::class)->applyForToday();

        $this->assertSame(800.0, (float) $trays->fresh()->current_stock);
    }

    public function test_matching_tray_stock_still_latches_daily_restock(): void
    {
        $trays = $this->item('Egg Trays', 500, 500);

        app(DailyInventory::class)->applyForToday();
        $this->assertDatabaseHas('inventory_item_histories', [
            'item_name' => 'Egg Trays',
            'action' => 'Checked',
            'note' => 'Daily tray restock',
        ]);

        $trays->update([
            'current_stock' => 420,
            'daily_stock' => 500,
        ]);

        app(DailyInventory::class)->applyForToday();

        // Harvest-style deduction must not be wiped after the daily latch.
        $this->assertSame(420.0, (float) $trays->fresh()->current_stock);
        $this->assertSame(
            1,
            InventoryItemHistory::query()
                ->where('inventory_item_id', $trays->id)
                ->where('note', 'Daily tray restock')
                ->count()
        );
    }

    private function item(string $name, float $current, ?float $daily = null): InventoryItem
    {
        return InventoryItem::create([
            'item_name' => $name,
            'unit' => $name === 'Vitamins' ? 'bottles' : ($name === 'Feeds' ? 'sacks' : 'pcs'),
            'current_stock' => $current,
            'min_level' => 10,
            'daily_stock' => $daily,
        ]);
    }
}
