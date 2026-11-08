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

    public function test_every_dashboard_shows_farm_totals_from_saved_records(): void
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
        Transaction::create([
            'customer' => 'Old Store',
            'transaction_date' => today()->subDays(30)->toDateString(),
            'quantity' => 1,
            'price' => 150.50,
            'status' => 'Pending',
        ]);

        $owner = User::factory()->create(['role' => 'owner']);
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);

        $users = [
            'owner.dashboard' => $owner,
            'manager.dashboard' => $manager,
            'staff.dashboard' => $staff,
        ];

        foreach ($users as $route => $user) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSeeInOrder([
                    'Total Egg Production',
                    '390',
                    'Eggs produced',
                    'Available Inventory',
                    '30',
                    'Available egg stocks',
                    'Total Dispatch',
                    '60',
                    'Dispatched eggs',
                    'Total Revenue',
                    '₱190.50',
                    'Recorded sales revenue',
                    'Total Eggs Today',
                ]);
        }
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
