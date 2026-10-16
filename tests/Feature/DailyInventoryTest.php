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

    public function test_existing_daily_log_does_not_skip_tray_synchronization(): void
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

        $this->assertSame(85.0, (float) $feed->fresh()->current_stock);
        $this->assertSame(60.0, (float) $vitamins->fresh()->current_stock);
        $this->assertSame(500.0, (float) $trays->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_item_histories', [
            'item_name' => 'Egg Trays',
            'action' => 'Reset',
        ]);
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
