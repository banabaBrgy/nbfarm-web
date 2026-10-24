<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_restock_existing_items_but_cannot_add_new_or_delete(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 100,
            'min_level' => 2,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.inventory'))
            ->assertOk()
            ->assertDontSee('id="stockModal"', false)
            ->assertDontSee('Add New Item')
            ->assertDontSee('openStockModal')
            ->assertSee('id="increaseModal"', false)
            ->assertSee('icon-btn-edit')
            ->assertDontSee('icon-btn-delete');

        $this->actingAs($staff)
            ->post('/staff/inventory/add-stock', [
                'item_name' => 'Vitamins',
                'unit' => 'bottles',
                'current_stock' => 5,
                'min_level' => 1,
            ])
            ->assertNotFound();

        // Inventory page applies the daily -15 feeds use before restock.
        $this->actingAs($staff)->get(route('staff.inventory'))->assertOk();
        $this->assertSame(85.0, (float) $item->fresh()->current_stock);

        $this->actingAs($staff)
            ->post(route('staff.inventory.increaseStock'), [
                'item_id' => $item->id,
                'quantity' => 3,
            ])
            ->assertRedirect();

        $this->assertSame(88.0, (float) $item->fresh()->current_stock);

        $this->actingAs($staff)
            ->get(route('staff.inventory.history', $item))
            ->assertOk()
            ->assertJsonPath('item', 'Feeds');

        $this->actingAs($staff)
            ->delete("/staff/inventory/delete/{$item->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }

    public function test_owner_can_add_restock_delete_and_view_history(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $item = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 100,
            'min_level' => 2,
        ]);
        InventoryItemHistory::record($item->id, 'Feeds', 'Restocked', 2, 8, 10, 'Test history');

        $this->actingAs($owner)
            ->get(route('owner.inventory'))
            ->assertOk()
            ->assertSee('icon-btn-delete')
            ->assertSee('icon-btn-edit')
            ->assertSee('id="stockModal"', false)
            ->assertSee('id="increaseModal"', false);

        $this->actingAs($owner)
            ->get(route('owner.inventory.history', $item))
            ->assertOk()
            ->assertJsonPath('history.0.action', 'Restocked');

        $this->actingAs($owner)
            ->post(route('owner.inventory.addStock'), [
                'item_name' => 'Vitamins',
                'unit' => 'bottles',
                'current_stock' => 5,
                'min_level' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_items', [
            'item_name' => 'Vitamins',
            'current_stock' => 5,
        ]);

        // Inventory page applies the daily -15 feeds use before restock.
        $this->actingAs($owner)->get(route('owner.inventory'))->assertOk();
        $this->assertSame(85.0, (float) $item->fresh()->current_stock);

        $this->actingAs($owner)
            ->post(route('owner.inventory.increaseStock'), [
                'item_id' => $item->id,
                'quantity' => 3,
            ])
            ->assertRedirect();

        $this->assertSame(88.0, (float) $item->fresh()->current_stock);

        $this->actingAs($owner)
            ->delete(route('owner.inventory.delete', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
    }

    public function test_manager_can_add_and_restock_but_cannot_delete_inventory(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $item = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 100,
            'min_level' => 2,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.inventory'))
            ->assertOk()
            ->assertSee('icon-btn-edit')
            ->assertDontSee('icon-btn-delete')
            ->assertSee('id="stockModal"', false)
            ->assertSee('id="increaseModal"', false);

        $this->actingAs($manager)
            ->get(route('manager.inventory.history', $item))
            ->assertOk();

        $this->actingAs($manager)
            ->post(route('manager.inventory.addStock'), [
                'item_name' => 'Vitamins',
                'unit' => 'bottles',
                'current_stock' => 5,
                'min_level' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_items', [
            'item_name' => 'Vitamins',
            'current_stock' => 5,
        ]);

        $this->actingAs($manager)->get(route('manager.inventory'))->assertOk();
        $this->assertSame(85.0, (float) $item->fresh()->current_stock);

        $this->actingAs($manager)
            ->post(route('manager.inventory.increaseStock'), [
                'item_id' => $item->id,
                'quantity' => 3,
            ])
            ->assertRedirect();

        $this->assertSame(88.0, (float) $item->fresh()->current_stock);

        $this->actingAs($manager)
            ->delete("/manager/inventory/delete/{$item->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }
}
