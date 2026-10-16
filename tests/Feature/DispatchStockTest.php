<?php

namespace Tests\Feature;

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

    public function test_owner_cannot_create_dispatches(): void
    {
        $response = $this->actingAs($this->owner())
            ->post('/owner/dispatch/store', $this->payload());

        $response->assertNotFound();
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

    private function payload(string $size = 'Large'): array
    {
        return [
            'recipient' => 'Test Store',
            'address' => 'Test Address',
            'dispatch_date' => today()->toDateString(),
            'price' => 100,
            'status' => 'Pending',
            'size' => [$size],
            'quantity' => [1],
        ];
    }
}
