<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryEggTraysStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_egg_trays_card_shows_inventory_stock_and_low_alert(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 50,
            'min_level' => 100,
            'daily_stock' => 50,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.inventory'))
            ->assertOk()
            ->assertSee('stat-card--inventory stat-card-alert', false)
            ->assertSee('pcs — Min: 100.00 — LOW', false)
            ->assertDontSee('icon-btn-edit')
            ->assertDontSee('id="increaseModal"', false)
            ->assertDontSee('id="stockModal"', false);
    }

    public function test_egg_trays_card_clears_alert_when_stock_is_ok(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 350,
            'min_level' => 100,
            'daily_stock' => 350,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.inventory'))
            ->assertOk()
            ->assertSee('pcs — Min: 100.00 — OK', false)
            ->assertDontSee('stat-card--inventory stat-card-alert', false);
    }

    public function test_inventory_page_works_when_egg_trays_item_is_missing(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.inventory'))
            ->assertOk()
            ->assertSee('Egg Trays', false)
            ->assertSee('pcs — Min: 0.00 — OK', false);
    }

    public function test_owner_restock_updates_egg_trays_display_and_clears_alert(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $trays = InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 50,
            'min_level' => 100,
            'daily_stock' => 50,
        ]);

        $this->actingAs($owner)
            ->get(route('owner.inventory'))
            ->assertOk()
            ->assertSee('stat-card--inventory stat-card-alert', false)
            ->assertSee('pcs — Min: 100.00 — LOW', false);

        $this->actingAs($owner)
            ->post(route('owner.inventory.increaseStock'), [
                'item_id' => $trays->id,
                'quantity' => 300,
            ])
            ->assertRedirect();

        $trays->refresh();
        $this->assertSame(350.0, (float) $trays->current_stock);
        $this->assertSame(350.0, (float) $trays->daily_stock);

        $this->actingAs($owner)
            ->get(route('owner.inventory'))
            ->assertOk()
            ->assertSee('pcs — Min: 100.00 — OK', false)
            ->assertDontSee('stat-card--inventory stat-card-alert', false);
    }

    public function test_manager_restock_updates_egg_trays_display(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $trays = InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 50,
            'min_level' => 100,
            'daily_stock' => 50,
        ]);

        $this->actingAs($manager)
            ->post(route('manager.inventory.increaseStock'), [
                'item_id' => $trays->id,
                'quantity' => 100,
            ])
            ->assertRedirect();

        $this->assertSame(150.0, (float) $trays->fresh()->current_stock);

        $this->actingAs($manager)
            ->get(route('manager.inventory'))
            ->assertOk()
            ->assertSee('pcs — Min: 100.00 — OK', false);
    }

    public function test_stock_history_hides_daily_checked_latch_entries(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $trays = InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 500,
            'min_level' => 100,
            'daily_stock' => 500,
        ]);

        InventoryItemHistory::record(
            $trays->id,
            'Egg Trays',
            'Checked',
            0,
            500,
            500,
            'Daily tray restock'
        );
        InventoryItemHistory::record(
            $trays->id,
            'Egg Trays',
            'Restocked',
            100,
            500,
            600,
            'Stock added'
        );

        $this->actingAs($owner)
            ->get(route('owner.inventory.history', $trays))
            ->assertOk()
            ->assertJsonPath('history.0.action', 'Restocked')
            ->assertJsonMissing(['action' => 'Checked']);
    }
}
