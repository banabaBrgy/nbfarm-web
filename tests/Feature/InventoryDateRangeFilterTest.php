<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDateRangeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_inventory_page_shows_date_range_filter_options(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->seedItem();

        $this->actingAs($owner)
            ->get(route('owner.inventory'))
            ->assertOk()
            ->assertSee('Daily')
            ->assertSee('Weekly')
            ->assertSee('Monthly')
            ->assertSee('Custom Date Range')
            ->assertSee('Inventory Records')
            ->assertSee('All inventory records');
    }

    public function test_date_range_filters_inventory_records(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $item = $this->seedItem();

        $this->seedHistory($item, 'Restocked today', 5, '2026-10-08 08:00:00');
        $this->seedHistory($item, 'Restocked this week', 3, '2026-10-06 10:00:00');
        $this->seedHistory($item, 'Restocked this month', 2, '2026-10-02 10:00:00');
        $this->seedHistory($item, 'Restocked last month', 4, '2026-09-15 10:00:00');

        $this->actingAs($manager)
            ->get(route('manager.inventory', ['range' => 'daily']))
            ->assertOk()
            ->assertSee('Restocked today')
            ->assertDontSee('Restocked this week')
            ->assertDontSee('Restocked last month');

        $this->actingAs($manager)
            ->get(route('manager.inventory', ['range' => 'weekly']))
            ->assertOk()
            ->assertSee('Restocked today')
            ->assertSee('Restocked this week')
            ->assertDontSee('Restocked this month')
            ->assertDontSee('Restocked last month');

        $this->actingAs($manager)
            ->get(route('manager.inventory', ['range' => 'monthly']))
            ->assertOk()
            ->assertSee('Restocked today')
            ->assertSee('Restocked this week')
            ->assertSee('Restocked this month')
            ->assertDontSee('Restocked last month');

        $this->actingAs($manager)
            ->get(route('manager.inventory', [
                'range' => 'custom',
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('Restocked last month')
            ->assertDontSee('Restocked today')
            ->assertDontSee('Restocked this month');
    }

    public function test_item_history_endpoint_respects_date_range(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item = $this->seedItem();

        $this->seedHistory($item, 'In range', 5, '2026-10-08 08:00:00');
        $this->seedHistory($item, 'Out of range', 2, '2026-09-01 08:00:00');

        $this->actingAs($staff)
            ->get(route('staff.inventory.history', [
                'id' => $item->id,
                'range' => 'daily',
            ]))
            ->assertOk()
            ->assertJsonPath('item', 'Feeds')
            ->assertJsonPath('period', 'Daily · Oct 8, 2026')
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.note', 'In range');
    }

    private function seedItem(): InventoryItem
    {
        return InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 100,
            'min_level' => 2,
        ]);
    }

    private function seedHistory(InventoryItem $item, string $note, float $change, string $when): void
    {
        $row = new InventoryItemHistory([
            'inventory_item_id' => $item->id,
            'item_name' => $item->item_name,
            'action' => 'Restocked',
            'quantity_change' => $change,
            'stock_before' => 10,
            'stock_after' => 10 + $change,
            'note' => $note,
        ]);
        $row->created_at = Carbon::parse($when);
        $row->updated_at = Carbon::parse($when);
        $row->save(['timestamps' => false]);
    }
}
