<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockNotification;
use App\Models\User;
use App\Services\DailyInventory;
use App\Services\LowStockNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username): User
    {
        return User::factory()->create([
            'username' => $username,
            'role' => $role,
        ]);
    }

    private function feeds(float $stock, float $min = 100): InventoryItem
    {
        return InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sacks',
            'current_stock' => $stock,
            'min_level' => $min,
        ]);
    }

    public function test_daily_deduct_notifies_owner_and_manager_once(): void
    {
        $owner = $this->user('owner', 'owner1');
        $manager = $this->user('manager', 'manager1');
        $this->user('staff', 'staff1');
        $this->feeds(100, 90);

        app(DailyInventory::class)->applyForToday();
        app(DailyInventory::class)->applyForToday();

        $this->assertSame(2, StockNotification::count());
        $this->assertTrue(
            StockNotification::where('user_id', $owner->id)->where('item_name', 'Feeds')->exists()
        );
        $this->assertTrue(
            StockNotification::where('user_id', $manager->id)->where('item_name', 'Feeds')->exists()
        );
        $this->assertFalse(
            StockNotification::whereHas('user', fn ($q) => $q->where('role', 'staff'))->exists()
        );
        $this->assertStringContainsString('Feeds is low', StockNotification::first()->body);
    }

    public function test_restock_clears_open_alerts(): void
    {
        $this->user('owner', 'owner1');
        $item = $this->feeds(50, 100);

        app(LowStockNotifier::class)->check($item);
        $this->assertSame(1, StockNotification::open()->count());

        $item->update(['current_stock' => 200]);
        app(LowStockNotifier::class)->check($item->fresh());

        $this->assertSame(0, StockNotification::open()->count());
    }

    public function test_owner_can_fetch_alerts_and_staff_cannot(): void
    {
        $owner = $this->user('owner', 'owner1');
        $staff = $this->user('staff', 'staff1');
        $item = $this->feeds(10, 50);
        app(LowStockNotifier::class)->check($item);

        $this->actingAs($owner)
            ->getJson(route('alerts.index'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonFragment(['title' => 'Low stock']);

        $alert = StockNotification::where('user_id', $owner->id)->first();

        $this->actingAs($owner)
            ->postJson(route('alerts.read', $alert))
            ->assertOk()
            ->assertJsonPath('unread', 0);

        $this->actingAs($staff)
            ->getJson(route('alerts.index'))
            ->assertForbidden();
    }

    public function test_owner_can_toggle_push_preference(): void
    {
        $owner = $this->user('owner', 'owner1');

        $this->actingAs($owner)
            ->postJson(route('alerts.push'), ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('push_enabled', true);

        $this->assertTrue($owner->fresh()->push_alerts_enabled);
    }
}
