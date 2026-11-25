<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnerDispatchScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_scan_page_shows_stock_by_size_and_pending_orders(): void
    {
        $this->production(['large_eggs' => 90, 'medium_eggs' => 60]);
        $this->pendingOrder('Store A', 'Large', 2);

        $html = $this->actingAs($this->owner())
            ->get(route('owner.dispatch.scan'))
            ->assertOk()
            ->assertSee('Scan for Dispatch')
            ->assertSee('Large')
            ->assertSee('Medium')
            ->assertSee('Store A')
            ->assertSee('js/html5-qrcode.min.js', false)
            ->assertSee('js/qr-camera.js', false)
            ->assertSee('js/farm-toast.js', false)
            ->assertSee('nbToast', false)
            ->assertSee('id="dispatchReader"', false)
            ->assertDontSee('unpkg.com/html5-qrcode', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-size="Large"[\s\S]*?>3<\/span>\s*trays/', $html);
        $this->assertMatchesRegularExpression('/data-size="Medium"[\s\S]*?>2<\/span>\s*trays/', $html);
    }

    public function test_manager_and_staff_cannot_open_owner_dispatch_scan(): void
    {
        $this->actingAs($this->manager())
            ->get(route('owner.dispatch.scan'))
            ->assertRedirect();

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('owner.dispatch.scan'))
            ->assertRedirect();
    }

    public function test_owner_dispatch_page_links_to_scan(): void
    {
        $this->actingAs($this->owner())
            ->get(route('owner.dispatch'))
            ->assertOk()
            ->assertSee('Scan for Dispatch')
            ->assertSee(route('owner.dispatch.scan'), false);
    }

    public function test_scan_loads_trays_deducts_stock_and_keeps_pending_until_complete(): void
    {
        $this->production(['large_eggs' => 90]);
        $this->ensureSizeQr('Large', 1);
        $dispatch = $this->pendingOrder('Store A', 'Large', 2);

        $this->assertSame(3, Production::availableDispatchTraysBySize()['Large']);

        $this->actingAs($this->owner())
            ->postJson(route('owner.dispatch.scan.apply'), [
                'qr_code' => 'QRL-B1',
                'dispatch_id' => $dispatch->id,
                'trays' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result.completed', false)
            ->assertJsonPath('result.status', 'Pending');

        $this->assertDatabaseHas('dispatch_items', [
            'dispatch_id' => $dispatch->id,
            'size' => 'Large',
            'quantity' => 2,
            'loaded_quantity' => 1,
        ]);
        $this->assertSame(2, Production::availableDispatchTraysBySize()['Large']);
        $this->assertSame('Pending', $dispatch->fresh()->status);
    }

    public function test_completing_order_sets_on_transit_and_reflects_for_manager(): void
    {
        $this->production(['large_eggs' => 60]);
        $this->ensureSizeQr('Large', 1);
        $dispatch = $this->pendingOrder('Store B', 'Large', 2);

        $this->actingAs($this->owner())
            ->postJson(route('owner.dispatch.scan.apply'), [
                'qr_code' => 'QRL-B1',
                'dispatch_id' => $dispatch->id,
                'trays' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('result.completed', true)
            ->assertJsonPath('result.status', 'On Transit');

        $this->assertSame('On Transit', $dispatch->fresh()->status);
        $this->assertSame(0, Production::availableDispatchTraysBySize()['Large']);

        $this->actingAs($this->manager())
            ->get(route('manager.dispatch', ['status' => 'On Transit']))
            ->assertOk()
            ->assertSee('Store B')
            ->assertSee('On Transit');
    }

    public function test_pending_order_still_blocks_second_manager_create_before_scan(): void
    {
        $this->production(['large_eggs' => 30]);

        $this->actingAs($this->manager())
            ->post(route('manager.dispatch.store'), $this->payload('Large'))
            ->assertRedirect(route('manager.dispatch'));

        // Physical stock still shows 1 tray until scanned.
        $this->assertSame(1, Production::availableDispatchTraysBySize()['Large']);
        // But ordered trays are already promised.
        $this->assertSame(0, Production::promiseableDispatchTraysBySize()['Large']);

        $this->actingAs($this->manager())
            ->post(route('manager.dispatch.store'), $this->payload('Large'))
            ->assertSessionHasErrors('quantity');
    }

    public function test_cannot_scan_more_trays_than_order_remaining(): void
    {
        $this->production(['large_eggs' => 90]);
        $this->ensureSizeQr('Large', 1);
        $dispatch = $this->pendingOrder('Store C', 'Large', 1);

        $this->actingAs($this->owner())
            ->postJson(route('owner.dispatch.scan.apply'), [
                'qr_code' => 'QRL-B1',
                'dispatch_id' => $dispatch->id,
                'trays' => 2,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_cannot_scan_when_physical_stock_is_empty(): void
    {
        $this->production(['large_eggs' => 0]);
        $this->ensureSizeQr('Large', 1);
        $dispatch = $this->pendingOrder('Store D', 'Large', 1);

        $this->actingAs($this->owner())
            ->postJson(route('owner.dispatch.scan.apply'), [
                'qr_code' => 'QRL-B1',
                'dispatch_id' => $dispatch->id,
                'trays' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_manager_cannot_post_owner_dispatch_scan_apply(): void
    {
        $this->production(['large_eggs' => 30]);
        $this->ensureSizeQr('Large', 1);
        $dispatch = $this->pendingOrder('Store E', 'Large', 1);

        $this->actingAs($this->manager())
            ->postJson(route('owner.dispatch.scan.apply'), [
                'qr_code' => 'QRL-B1',
                'dispatch_id' => $dispatch->id,
                'trays' => 1,
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('dispatch_items', [
            'dispatch_id' => $dispatch->id,
            'loaded_quantity' => 0,
        ]);
    }

    public function test_duplicate_sizes_on_create_are_rejected(): void
    {
        $this->production(['large_eggs' => 60]);
        $payload = $this->payload('Large');
        $payload['size'] = ['Large', 'Large'];
        $payload['quantity'] = [1, 1];

        $this->actingAs($this->manager())
            ->post(route('manager.dispatch.store'), $payload)
            ->assertSessionHasErrors('size');
    }

    public function test_manual_on_transit_loads_remaining_trays_from_physical_stock(): void
    {
        $this->production(['large_eggs' => 60]);
        $dispatch = $this->pendingOrder('Store F', 'Large', 2);

        $this->actingAs($this->owner())
            ->put(route('owner.dispatch.update', $dispatch), [
                'recipient' => 'Store F',
                'address' => 'Lipa City',
                'dispatch_date' => today()->toDateString(),
                'price' => 200,
                'status' => 'On Transit',
                'size' => ['Large'],
                'quantity' => [2],
            ])
            ->assertRedirect();

        $this->assertSame('On Transit', $dispatch->fresh()->status);
        $this->assertDatabaseHas('dispatch_items', [
            'dispatch_id' => $dispatch->id,
            'loaded_quantity' => 2,
        ]);
        $this->assertSame(0, Production::availableDispatchTraysBySize()['Large']);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function production(array $overrides = []): void
    {
        $row = array_merge([
            'production_date' => today()->toDateString(),
            'batch_id' => 1,
            'small_eggs' => 0,
            'medium_eggs' => 0,
            'large_eggs' => 0,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => 0,
        ], $overrides);

        if (! array_key_exists('eggs_produced', $overrides)) {
            $row['eggs_produced'] = (int) $row['small_eggs']
                + (int) $row['medium_eggs']
                + (int) $row['large_eggs']
                + (int) $row['extra_large_eggs']
                + (int) $row['cracked_eggs'];
        }

        DB::table('production')->insert($row);
    }

    private function pendingOrder(string $recipient, string $size, int $quantity): Dispatch
    {
        $dispatch = Dispatch::create([
            'recipient' => $recipient,
            'contact' => '09171234567',
            'address' => 'Lipa City',
            'dispatch_date' => today()->toDateString(),
            'price' => 100 * $quantity,
            'status' => 'Pending',
        ]);
        DispatchItem::create([
            'dispatch_id' => $dispatch->id,
            'size' => $size,
            'quantity' => $quantity,
            'loaded_quantity' => 0,
        ]);

        return $dispatch;
    }

    private function ensureSizeQr(string $size, int $batchId): QrRecord
    {
        return QrRecord::query()->create([
            'production_id' => null,
            'batch_id' => $batchId,
            'qr_code' => QrRecord::sizeCode($batchId, $size),
            'qr_date' => today()->toDateString(),
            'egg_size' => $size,
            'tray_count' => 1,
            'eggs_per_tray' => 30,
        ]);
    }

    private function payload(string $size = 'Large'): array
    {
        return [
            'recipient' => 'Test Store',
            'contact' => '09171234567',
            'address' => 'Test Address',
            'dispatch_date' => today()->toDateString(),
            'price' => 100,
            'status' => 'Pending',
            'size' => [$size],
            'quantity' => [1],
        ];
    }
}
