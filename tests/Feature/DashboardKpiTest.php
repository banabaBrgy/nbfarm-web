<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_owner_dashboard_does_not_show_lifetime_total_cards(): void
    {
        $this->harvest(today()->toDateString(), 90, 15);
        $this->harvest(today()->subDays(10)->toDateString(), 300, 0);

        $dispatch = Dispatch::create([
            'recipient' => 'North Store',
            'address' => 'Lipa City',
            'dispatch_date' => today()->toDateString(),
            'price' => 40,
            'status' => 'Pending',
        ]);
        DispatchItem::create([
            'dispatch_id' => $dispatch->id,
            'size' => 'Large',
            'quantity' => 2,
        ]);

        Transaction::create([
            'customer' => 'North Store',
            'transaction_date' => today()->toDateString(),
            'quantity' => 2,
            'price' => 40,
            'status' => 'Paid',
        ]);

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertDontSee('Total Egg Production')
            ->assertDontSee('Available Inventory')
            ->assertDontSee('Total Revenue')
            ->assertSee('Total Eggs Produced')
            ->assertSee('Eggs Stock')
            ->assertSee('Total Dispatch')
            ->assertSee('>Revenue<', false);
    }

    private function harvest(string $date, int $largeEggs, int $crackedEggs): void
    {
        DB::table('production')->insert([
            'production_date' => $date,
            'batch_id' => 1,
            'small_eggs' => 0,
            'medium_eggs' => 0,
            'large_eggs' => $largeEggs,
            'extra_large_eggs' => 0,
            'cracked_eggs' => $crackedEggs,
            'eggs_produced' => $largeEggs,
        ]);
    }
}
