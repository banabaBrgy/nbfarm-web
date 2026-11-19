<?php

namespace Tests\Unit;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Services\HistoricalInventory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_dates_read_ledger_stock_instead_of_current_stock(): void
    {
        Carbon::setTestNow('2026-11-08 09:00:00');

        $feeds = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sack',
            'current_stock' => 100,
            'min_level' => 20,
            'daily_stock' => 15,
        ]);

        $this->ledger($feeds, 'Restocked', 40, 0, 40, '2026-11-01 08:00:00');
        $this->ledger($feeds, 'Deducted', -10, 40, 30, '2026-11-03 08:00:00');

        $inventory = app(HistoricalInventory::class);

        $this->assertSame(100.0, $inventory->namedStock('Feeds', Carbon::parse('2026-11-08'), true));
        $this->assertSame(40.0, $inventory->namedStock('Feeds', Carbon::parse('2026-11-01'), false));
        $this->assertSame(30.0, $inventory->namedStock('Feeds', Carbon::parse('2026-11-05'), false));

        $range = $inventory->movements(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05'));
        $feeds = collect($range)->firstWhere('name', 'Feeds');
        $this->assertSame('Eggs', $range[0]['name']);
        $this->assertSame(40.0, $feeds['stockIn']);
        $this->assertSame(10.0, $feeds['stockOut']);

        $day = $inventory->movements(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-01'));
        $feedsOnDay = collect($day)->firstWhere('name', 'Feeds');
        $this->assertSame(40.0, $feedsOnDay['stockIn']);
        $this->assertSame(0.0, $feedsOnDay['stockOut']);

        Carbon::setTestNow();
    }

    private function ledger(
        InventoryItem $item,
        string $action,
        float $change,
        float $before,
        float $after,
        string $at,
    ): void {
        $history = InventoryItemHistory::create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->item_name,
            'action' => $action,
            'quantity_change' => $change,
            'stock_before' => $before,
            'stock_after' => $after,
        ]);

        InventoryItemHistory::query()->where('id', $history->id)->update([
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
