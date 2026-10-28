<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DispatchStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_dispatch_is_rejected_when_no_egg_stock_is_available(): void
    {
        $this->production(['large_eggs' => 0]);

        $response = $this->actingAs($this->manager())
            ->post(route('manager.dispatch.store'), $this->payload());

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_dispatch_must_match_the_available_egg_size(): void
    {
        $this->production(['large_eggs' => 30]);

        $response = $this->actingAs($this->manager())
            ->post(route('manager.dispatch.store'), $this->payload('Small'));

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_reserved_trays_cannot_be_dispatched_twice(): void
    {
        $this->production(['large_eggs' => 30]);

        $first = $this->actingAs($this->manager())
            ->post(route('manager.dispatch.store'), $this->payload('Large'));

        $first->assertRedirect(route('manager.dispatch'));

        $second = $this->actingAs($this->manager())
            ->post(route('manager.dispatch.store'), $this->payload('Large'));

        $second->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('dispatches', 1);
    }

    public function test_dispatch_date_must_be_inside_the_stock_window(): void
    {
        $this->production(['large_eggs' => 30]);
        $payload = $this->payload('Large');
        $payload['dispatch_date'] = today()->subDays(7)->toDateString();

        $response = $this->actingAs($this->manager())
            ->post(route('manager.dispatch.store'), $payload);

        $response->assertSessionHasErrors('dispatch_date');
        $this->assertDatabaseCount('dispatches', 0);
    }

    public function test_new_dispatch_requires_a_contact_number(): void
    {
        $this->production(['large_eggs' => 30]);
        $payload = $this->payload('Large');
        unset($payload['contact']);

        $response = $this->actingAs($this->manager())
            ->from(route('manager.dispatch'))
            ->post(route('manager.dispatch.store'), $payload);

        $response->assertRedirect(route('manager.dispatch'));
        $response->assertSessionHasErrors('contact');
        $this->assertDatabaseCount('dispatches', 0);

        $this->actingAs($this->manager())
            ->get(route('manager.dispatch'))
            ->assertOk()
            ->assertSee('name="contact"', false)
            ->assertSee('placeholder="09XX XXX XXXX"', false);
    }

    public function test_owner_cannot_create_dispatches(): void
    {
        $response = $this->actingAs($this->owner())
            ->post('/owner/dispatch/store', $this->payload());

        $response->assertStatus(405);
    }

    public function test_owner_can_edit_dispatch_details_and_items(): void
    {
        $this->production(['large_eggs' => 90, 'medium_eggs' => 30]);
        $dispatch = $this->existingDispatch();

        $response = $this->actingAs($this->owner())
            ->put(route('owner.dispatch.update', $dispatch), [
                'recipient' => 'Maria',
                'address' => 'Pusil, Lipa City',
                'dispatch_date' => today()->toDateString(),
                'price' => 300,
                'status' => 'Delivered',
                'size' => ['Medium'],
                'quantity' => [1],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dispatches', [
            'id' => $dispatch->id,
            'recipient' => 'Maria',
            'address' => 'Pusil, Lipa City',
            'price' => 300,
            'status' => 'Delivered',
        ]);
        $this->assertDatabaseHas('dispatch_items', [
            'dispatch_id' => $dispatch->id,
            'size' => 'Medium',
            'quantity' => 1,
        ]);
        $this->assertDatabaseMissing('dispatch_items', [
            'dispatch_id' => $dispatch->id,
            'size' => 'Large',
        ]);
    }

    public function test_owner_can_keep_an_old_dispatch_date_when_editing(): void
    {
        $dispatch = $this->existingDispatch([
            'dispatch_date' => today()->subDays(8)->toDateString(),
        ]);

        $response = $this->actingAs($this->owner())
            ->put(route('owner.dispatch.update', $dispatch), [
                'recipient' => 'Updated Store',
                'address' => 'Test Address',
                'dispatch_date' => today()->subDays(8)->toDateString(),
                'price' => 100,
                'status' => 'Delivered',
                'size' => ['Large'],
                'quantity' => [1],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dispatches', [
            'id' => $dispatch->id,
            'recipient' => 'Updated Store',
            'status' => 'Delivered',
        ]);
    }

    public function test_owner_cannot_increase_trays_beyond_available_stock(): void
    {
        $this->production(['large_eggs' => 30]);
        $dispatch = $this->existingDispatch();

        $response = $this->actingAs($this->owner())
            ->from(route('owner.dispatch'))
            ->put(route('owner.dispatch.update', $dispatch), [
                'recipient' => 'Test Store',
                'address' => 'Test Address',
                'dispatch_date' => today()->toDateString(),
                'price' => 100,
                'status' => 'Pending',
                'size' => ['Large'],
                'quantity' => [2],
            ]);

        $response->assertRedirect(route('owner.dispatch'));
        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseHas('dispatch_items', [
            'dispatch_id' => $dispatch->id,
            'size' => 'Large',
            'quantity' => 1,
        ]);
    }

    public function test_owner_dispatch_page_keeps_view_and_edit_controls(): void
    {
        $dispatch = Dispatch::create([
            'recipient' => 'Test Store',
            'address' => 'Test Address',
            'dispatch_date' => today()->toDateString(),
            'price' => 100,
            'status' => 'Pending',
        ]);
        DispatchItem::create([
            'dispatch_id' => $dispatch->id,
            'size' => 'Large',
            'quantity' => 1,
        ]);

        $this->actingAs($this->owner())
            ->get(route('owner.dispatch'))
            ->assertOk()
            ->assertSee('title="Edit"', false)
            ->assertSee('title="View"', false)
            ->assertSee('Edit Dispatch')
            ->assertSee('Dispatch Details')
            ->assertSee('name="recipient"', false)
            ->assertSee('name="size[]"', false)
            ->assertDontSee('Size and trays cannot be changed here.');
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function production(array $overrides = []): void
    {
        DB::table('production')->insert(array_merge([
            'production_date' => today()->toDateString(),
            'batch_id' => 1,
            'small_eggs' => 0,
            'medium_eggs' => 0,
            'large_eggs' => 0,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => 0,
        ], $overrides));
    }

    private function existingDispatch(array $overrides = []): Dispatch
    {
        $dispatch = Dispatch::create(array_merge([
            'recipient' => 'Test Store',
            'address' => 'Test Address',
            'dispatch_date' => today()->toDateString(),
            'price' => 100,
            'status' => 'Pending',
        ], $overrides));

        DispatchItem::create([
            'dispatch_id' => $dispatch->id,
            'size' => 'Large',
            'quantity' => 1,
        ]);

        return $dispatch;
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
