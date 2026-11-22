<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMyActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-08 14:30:00');
        $this->bindForecastCollaborators();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_dashboard_shows_my_activity_instead_of_the_line_chart(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('My Activity')
            ->assertSee('Your recent activities in the system')
            ->assertSee('All Activities')
            ->assertSee('data-activity-filter="production"', false)
            ->assertSee('data-activity-filter="inventory"', false)
            ->assertSee('No production or inventory activity yet today.')
            ->assertSee('Inventory Levels')
            ->assertDontSee('id="trendView"', false)
            ->assertDontSee('id="eggChart"', false)
            ->assertDontSee('Seven-Day Forecast')
            ->assertDontSee('chart.umd.min.js', false);
    }

    public function test_owner_and_manager_dashboards_keep_the_production_chart(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('id="trendView"', false)
            ->assertSee('Seven-Day Forecast')
            ->assertDontSee('My Activity');

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('id="trendView"', false)
            ->assertSee('Seven-Day Forecast')
            ->assertDontSee('My Activity');
    }

    public function test_staff_sees_own_scans_and_restocks_newest_first(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create(['role' => 'staff']);
        $feeds = $this->feeds();

        $production = $this->harvest('2026-11-08', 120);
        $this->scan($staff, $production, 'Large', 120, '2026-11-08 09:15:00', 'QRL-B1-001');
        $this->restock($staff, $feeds, 5, '2026-11-08 10:00:00');
        $this->restock($other, $feeds, 99, '2026-11-08 11:00:00');
        $this->scan($other, $this->harvest('2026-11-08', 60, 2), 'Small', 60, '2026-11-08 11:30:00', 'QRS-B2-001');

        InventoryItemHistory::create([
            'inventory_item_id' => $feeds->id,
            'item_name' => 'Hidden Trays',
            'action' => 'Deducted',
            'quantity_change' => -4,
            'stock_before' => 20,
            'stock_after' => 16,
            'note' => 'Trays used for harvest',
            'user_id' => $staff->id,
            'created_at' => '2026-11-08 09:16:00',
            'updated_at' => '2026-11-08 09:16:00',
        ]);

        $html = $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('My Activity')
            ->assertSee('All Activities')
            ->assertSee('Recorded production')
            ->assertSee('Batch 1 • Large • 120 eggs')
            ->assertSee('Updated inventory')
            ->assertSee('Restocked Feeds • +5')
            ->assertSee('data-activity-group="production"', false)
            ->assertSee('data-activity-group="inventory"', false)
            ->assertSee('9:15 AM')
            ->assertSee('10:00 AM')
            ->assertDontSee('Restocked Feeds • +99')
            ->assertDontSee('Batch 2 • Small • 60 eggs')
            ->assertDontSee('Hidden Trays')
            ->getContent();

        $this->assertTrue(
            strpos($html, 'Updated inventory') < strpos($html, 'Recorded production'),
            'Newest restock should appear above the earlier scan.'
        );
    }

    public function test_staff_sees_production_edits_and_date_filter_hides_other_days(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $feeds = $this->feeds();
        $today = $this->harvest('2026-11-08', 90);
        $past = $this->harvest('2026-11-01', 300, 1);

        $this->scan($staff, $today, 'Medium', 90, '2026-11-08 08:00:00', 'QRM-B1-001');
        $this->edit($staff, $today, 'Extra Large', 30, '2026-11-08 08:20:00', 'QRXL-B1-001');
        $this->restock($staff, $feeds, 3, '2026-11-01 07:45:00');
        $this->scan($staff, $past, 'Small', 300, '2026-11-01 08:10:00', 'QRS-B1-002');

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Recorded production')
            ->assertSee('Batch 1 • Medium • 90 eggs')
            ->assertSee('Updated production')
            ->assertSee('Batch 1 • Extra Large • 30 eggs')
            ->assertDontSee('Restocked Feeds • +3')
            ->assertDontSee('Batch 1 • Small • 300 eggs');

        $this->actingAs($staff)
            ->get(route('staff.dashboard', [
                'dash_period' => 'date',
                'dash_date' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('Activity for Nov 1, 2026')
            ->assertSee('Restocked Feeds • +3')
            ->assertSee('Batch 1 • Small • 300 eggs')
            ->assertSee('Nov 1, 7:45 AM')
            ->assertDontSee('Batch 1 • Medium • 90 eggs')
            ->assertDontSee('Batch 1 • Extra Large • 30 eggs')
            ->assertDontSee('No production or inventory activity yet today.');
    }

    public function test_scan_then_edit_of_the_same_record_shows_one_activity(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $production = $this->harvest('2026-11-08', 120);
        $transaction = $this->scan($staff, $production, 'Large', 120, '2026-11-08 09:00:00', 'QRL-B1-010');

        QrTransaction::query()->where('id', $transaction->id)->update([
            'total_eggs' => 90,
            'status' => 'Edited',
            'edited_by' => $staff->id,
            'edited_at' => '2026-11-08 09:20:00',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Updated production')
            ->assertSee('Batch 1 • Large • 90 eggs')
            ->assertDontSee('Recorded production');
    }

    public function test_feed_keeps_the_newest_fifteen_across_types(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $feeds = $this->feeds();
        $production = $this->harvest('2026-11-08', 500);

        foreach (range(1, 16) as $n) {
            $this->scan(
                $staff,
                $production,
                'Small',
                30 + $n,
                sprintf('2026-11-08 08:%02d:00', $n),
                sprintf('QRS-B1-%03d', $n),
            );
        }

        $this->restock($staff, $feeds, 7, '2026-11-08 09:00:00');

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Restocked Feeds • +7')
            ->assertSee('46 eggs')
            ->assertSee('33 eggs')
            ->assertDontSee('32 eggs')
            ->assertDontSee('31 eggs');
    }

    public function test_date_range_includes_activity_from_both_ends(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $feeds = $this->feeds();

        $this->scan(
            $staff,
            $this->harvest('2026-11-01', 75),
            'Medium',
            75,
            '2026-11-01 08:00:00',
            'QRM-B1-020',
        );
        $this->restock($staff, $feeds, 4, '2026-11-08 16:00:00');

        $this->actingAs($staff)
            ->get(route('staff.dashboard', [
                'dash_period' => 'range',
                'dash_from' => '2026-11-01',
                'dash_to' => '2026-11-08',
            ]))
            ->assertOk()
            ->assertSee('Activity for Nov 1 – Nov 8, 2026')
            ->assertSee('Batch 1 • Medium • 75 eggs')
            ->assertSee('Restocked Feeds • +4')
            ->assertSee('Nov 1, 8:00 AM')
            ->assertSee('4:00 PM');
    }

    private function feeds(): InventoryItem
    {
        return InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sack',
            'current_stock' => 40,
            'min_level' => 10,
            'daily_stock' => 15,
        ]);
    }

    private function harvest(string $date, int $eggs, int $batchId = 1): Production
    {
        return Production::query()->create([
            'production_date' => $date,
            'batch_id' => $batchId,
            'small_eggs' => 0,
            'medium_eggs' => 0,
            'large_eggs' => $eggs,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => $eggs,
        ]);
    }

    private function scan(
        User $staff,
        Production $production,
        string $size,
        int $eggs,
        string $at,
        string $code,
    ): QrTransaction {
        $record = QrRecord::create([
            'production_id' => $production->production_id,
            'batch_id' => $production->batch_id,
            'qr_code' => $code,
            'qr_date' => $production->production_date,
            'egg_size' => $size,
            'tray_count' => max(1, intdiv($eggs, 30)),
            'eggs_per_tray' => 30,
        ]);

        $transaction = QrTransaction::create([
            'qr_record_id' => $record->id,
            'production_id' => $production->production_id,
            'total_eggs' => $eggs,
            'status' => 'Scanned',
            'scanned_by' => $staff->id,
            'scanned_at' => $at,
        ]);

        QrTransaction::query()->where('id', $transaction->id)->update([
            'scanned_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return $transaction->fresh();
    }

    private function edit(
        User $staff,
        Production $production,
        string $size,
        int $eggs,
        string $at,
        string $code,
    ): QrTransaction {
        $transaction = $this->scan($staff, $production, $size, $eggs, $at, $code);

        QrTransaction::query()->where('id', $transaction->id)->update([
            'status' => 'Edited',
            'edited_by' => $staff->id,
            'edited_at' => $at,
        ]);

        return $transaction->fresh();
    }

    private function restock(User $staff, InventoryItem $item, float $qty, string $at): void
    {
        $history = InventoryItemHistory::create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->item_name,
            'action' => 'Restocked',
            'quantity_change' => $qty,
            'stock_before' => 10,
            'stock_after' => 10 + $qty,
            'note' => 'Stock added',
            'user_id' => $staff->id,
        ]);

        InventoryItemHistory::query()->where('id', $history->id)->update([
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function bindForecastCollaborators(): void
    {
        $weather = $this->createMock(WeatherService::class);
        $weather->method('temperatureForDate')->willReturn(28.0);
        $weather->method('current')->willReturn(null);
        $this->app->instance(WeatherService::class, $weather);

        $this->app->instance(ForecastEngine::class, new class implements ForecastEngine
        {
            public function predict(
                array $history,
                array $predictDates,
                int $window = 7,
                array $temperatures = [],
                array $options = [],
            ): array {
                return [
                    'predicted_eggs' => 0,
                    'forecast_date' => $predictDates[0] ?? '2026-11-09',
                    'sample_days' => 0,
                    'temperature' => 28.0,
                    'model' => 'test',
                    'series' => [],
                    'error' => null,
                ];
            }
        });
    }
}
