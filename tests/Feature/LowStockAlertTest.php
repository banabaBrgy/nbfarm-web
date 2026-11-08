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
        $this->assertStringContainsString('Feeds is critically low', StockNotification::first()->body);
        $this->assertSame('high', StockNotification::first()->priority);
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
            ->assertJsonFragment(['title' => 'Critical low stock', 'priority' => 'high', 'priority_label' => 'High']);

        $alert = StockNotification::where('user_id', $owner->id)->first();

        $this->actingAs($owner)
            ->postJson(route('alerts.read', $alert))
            ->assertOk()
            ->assertJsonPath('unread', 0);

        $this->actingAs($owner)
            ->postJson(route('alerts.unread', $alert))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('read', false);

        $this->assertNull($alert->fresh()->read_at);

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

    public function test_stock_approaching_minimum_is_medium_and_a_further_drop_becomes_high(): void
    {
        $owner = $this->user('owner', 'owner1');
        $item = $this->feeds(110, 100);

        app(LowStockNotifier::class)->check($item);

        $alert = StockNotification::where('user_id', $owner->id)->first();
        $this->assertSame('medium', $alert->priority);
        $this->assertSame('Stock approaching minimum', $alert->title);
        $this->assertStringContainsString('approaching minimum', $alert->body);

        $alert->update(['read_at' => now()]);
        $item->update(['current_stock' => 90]);
        app(LowStockNotifier::class)->check($item->fresh());

        $this->assertSame(1, StockNotification::count());
        $alert->refresh();
        $this->assertSame('high', $alert->priority);
        $this->assertSame('Critical low stock', $alert->title);
        $this->assertNull($alert->read_at);
        $this->assertNull($alert->resolved_at);
    }

    public function test_exact_minimum_is_medium_and_a_safe_level_stays_quiet(): void
    {
        $this->user('owner', 'owner1');

        app(LowStockNotifier::class)->check($this->feeds(100, 100));
        $this->assertSame('medium', StockNotification::first()->priority);

        $safe = InventoryItem::create([
            'item_name' => 'Vitamins',
            'unit' => 'bottles',
            'current_stock' => 120,
            'min_level' => 100,
        ]);
        app(LowStockNotifier::class)->check($safe);

        $this->assertSame(1, StockNotification::count());
    }

    public function test_partial_restock_into_the_watch_band_becomes_medium(): void
    {
        $this->user('owner', 'owner1');
        $item = $this->feeds(40, 100);

        app(LowStockNotifier::class)->check($item);

        $item->update(['current_stock' => 110]);
        app(LowStockNotifier::class)->check($item->fresh());

        $alert = StockNotification::first();
        $this->assertNull($alert->resolved_at);
        $this->assertSame('medium', $alert->priority);
        $this->assertSame('Stock approaching minimum', $alert->title);
    }

    public function test_panel_lists_high_before_newer_lower_priorities(): void
    {
        $owner = $this->user('owner', 'owner1');

        $low = StockNotification::create([
            'user_id' => $owner->id,
            'type' => 'report',
            'priority' => 'low',
            'item_name' => 'Report',
            'title' => 'New report',
            'body' => 'A report was sent.',
            'url' => '/owner/reports/1',
        ]);
        $medium = StockNotification::create([
            'user_id' => $owner->id,
            'type' => 'low_stock',
            'priority' => 'medium',
            'item_name' => 'Vitamins',
            'title' => 'Stock approaching minimum',
            'body' => 'Vitamins is approaching minimum.',
            'url' => '/owner/inventory',
        ]);
        $high = StockNotification::create([
            'user_id' => $owner->id,
            'type' => 'low_stock',
            'priority' => 'high',
            'item_name' => 'Feeds',
            'title' => 'Critical low stock',
            'body' => 'Feeds is critically low.',
            'url' => '/owner/inventory',
        ]);

        $low->forceFill(['created_at' => now()->addMinute()])->save();
        $medium->forceFill(['created_at' => now()])->save();
        $high->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($owner)
            ->getJson(route('alerts.index'))
            ->assertOk()
            ->assertJsonPath('alerts.0.priority', 'high')
            ->assertJsonPath('alerts.0.priority_label', 'High')
            ->assertJsonPath('alerts.1.priority', 'medium')
            ->assertJsonPath('alerts.1.priority_label', 'Medium')
            ->assertJsonPath('alerts.2.priority', 'low')
            ->assertJsonPath('alerts.2.priority_label', 'Low');
    }
}
