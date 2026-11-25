<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardPendingDispatchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manager_dashboard_lists_recent_dispatches_like_the_mockup(): void
    {
        $pending = Dispatch::create([
            'recipient' => 'City Mart',
            'address' => 'Batangas city',
            'dispatch_date' => '2026-11-23',
            'price' => 2000,
            'status' => 'Pending',
        ]);
        DispatchItem::create([
            'dispatch_id' => $pending->id,
            'size' => 'Large',
            'quantity' => 15,
        ]);
        DispatchItem::create([
            'dispatch_id' => $pending->id,
            'size' => 'Medium',
            'quantity' => 20,
        ]);

        $delivered = Dispatch::create([
            'recipient' => 'Bolbok Store',
            'address' => 'Bolbok batangas',
            'dispatch_date' => '2026-11-22',
            'price' => 1500,
            'status' => 'Delivered',
        ]);
        DispatchItem::create([
            'dispatch_id' => $delivered->id,
            'size' => 'Medium',
            'quantity' => 10,
        ]);
        DispatchItem::create([
            'dispatch_id' => $delivered->id,
            'size' => 'Small',
            'quantity' => 5,
        ]);

        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Manager Dashboard')
            ->assertSee('Total Eggs Today')
            ->assertSee('predicted eggs')
            ->assertSee('Pending Dispatches')
            ->assertSee('(1)')
            ->assertSee('View All')
            ->assertSee('City Mart')
            ->assertSee('Batangas city')
            ->assertSee('15 trays')
            ->assertSee('Large')
            ->assertSee('₱2,000.00')
            ->assertDontSee('Bolbok Store')
            ->assertDontSee('Bolbok batangas')
            ->assertSee('Inventory Levels')
            ->assertDontSee('id="trendView"', false)
            ->assertDontSee('chart.umd.min.js', false);
    }
}
