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

    public function test_staff_can_edit_and_view_history_but_cannot_delete_inventory(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $item = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 10,
            'min_level' => 2,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.inventory'))
            ->assertOk()
            ->assertSee('icon-btn-edit')
            ->assertDontSee('icon-btn-delete');

        $this->actingAs($staff)
            ->post(route('staff.inventory.addStock'), [
                'item_name' => 'Vitamins',
                'unit' => 'bottles',
                'current_stock' => 5,
                'min_level' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($staff)
            ->post(route('staff.inventory.increaseStock'), [
                'item_id' => $item->id,
                'quantity' => 3,
            ])
            ->assertRedirect();

        $this->actingAs($staff)
            ->get(route('staff.inventory.history', $item))
            ->assertOk()
            ->assertJsonPath('item', 'Feeds');

        $this->actingAs($staff)
            ->delete("/staff/inventory/delete/{$item->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }

    public function test_owner_can_delete_and_view_history_but_cannot_edit_inventory(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $item = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 10,
            'min_level' => 2,
        ]);
        InventoryItemHistory::record($item->id, 'Feeds', 'Restocked', 2, 8, 10, 'Test history');

        $this->actingAs($owner)
            ->get(route('owner.inventory'))
            ->assertOk()
            ->assertSee('icon-btn-delete')
            ->assertDontSee('icon-btn-edit');

        $this->actingAs($owner)
            ->get(route('owner.inventory.history', $item))
            ->assertOk()
            ->assertJsonPath('history.0.action', 'Restocked');

        $this->actingAs($owner)
            ->post('/owner/inventory/add-stock', [
                'item_name' => 'Vitamins',
                'unit' => 'bottles',
                'current_stock' => 5,
                'min_level' => 1,
            ])
            ->assertStatus(405);

        $this->actingAs($owner)
            ->post('/owner/inventory/increase-stock', [
                'item_id' => $item->id,
                'quantity' => 3,
            ])
            ->assertStatus(405);

        $this->actingAs($owner)
            ->delete(route('owner.inventory.delete', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
    }

    public function test_manager_can_view_inventory_and_history_but_cannot_mutate_inventory(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $item = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => 10,
            'min_level' => 2,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.inventory'))
            ->assertOk()
            ->assertDontSee('icon-btn-edit')
            ->assertDontSee('icon-btn-delete');

        $this->actingAs($manager)
            ->get(route('manager.inventory.history', $item))
            ->assertOk();

        $this->actingAs($manager)
            ->post('/manager/inventory/add-stock', [
                'item_name' => 'Vitamins',
                'unit' => 'bottles',
                'current_stock' => 5,
                'min_level' => 1,
            ])
            ->assertNotFound();

        $this->actingAs($manager)
            ->post('/manager/inventory/increase-stock', [
                'item_id' => $item->id,
                'quantity' => 3,
            ])
            ->assertNotFound();

        $this->actingAs($manager)
            ->delete("/manager/inventory/delete/{$item->id}")
            ->assertNotFound();
    }
}
