<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\InventoryItem;
use App\Models\InventoryItemHistory;
use App\Models\Production;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-08 09:00:00');
        $this->bindForecastCollaborators();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_staff_manager_and_owner_dashboards_show_the_global_date_filter(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('id="dashFrom"', false)
            ->assertSee('id="dashTo"', false)
            ->assertSee('data-auto-submit', false)
            ->assertDontSee('form.submit();', false)
            ->assertSee('Nov 8, 2026')
            ->assertDontSee('data-period="today"', false)
            ->assertSee("Welcome back! Here's today's poultry farm overview.")
            ->assertSee('Total Eggs Today')
            ->assertSee('My Activity')
            ->assertDontSee('id="trendView"', false)
            ->assertSee('Inventory Levels')
            ->assertDontSee('Stock In');

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('id="dashFrom"', false)
            ->assertSee('Revenue Today');

        $this->actingAs($owner)
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('id="dashTo"', false)
            ->assertSee('Total Eggs Produced')
            ->assertSee('Eggs Stock')
            ->assertSee('Total Dispatch')
            ->assertSee('Revenue');
    }

    public function test_kpi_cards_follow_the_selected_date(): void
    {
        $this->harvest('2026-11-08', 5678);
        $this->harvest('2026-11-01', 1234);

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('<h2>5,678</h2>', false)
            ->assertDontSee('<h2>1,234</h2>', false);

        $this->actingAs($staff)
            ->get(route('staff.dashboard', [
                'dash_period' => 'date',
                'dash_date' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('Total Eggs')
            ->assertDontSee('Total Eggs Today')
            ->assertSee('<h2>1,234</h2>', false)
            ->assertDontSee('<h2>5,678</h2>', false)
            ->assertSee('Farm overview for November 1, 2026.')
            ->assertSee('Forecast from Nov 1, 2026')
            ->assertSee('For Nov 2, 2026');
    }

    public function test_date_range_totals_production_and_locks_the_chart(): void
    {
        $this->harvest('2026-11-01', 100);
        $this->harvest('2026-11-02', 50);
        $this->harvest('2026-11-08', 900);

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'dash_period' => 'range',
                'dash_from' => '2026-11-01',
                'dash_to' => '2026-11-02',
            ]))
            ->assertOk()
            ->assertSee('<h2>150</h2>', false)
            ->assertDontSee('<h2>900</h2>', false)
            ->assertSee('Farm overview for Nov 1 – Nov 2, 2026.')
            ->assertSee('value="historical" selected', false)
            ->assertSee('id="trendDateRange"', false)
            ->assertSee('style="display:none;"', false)
            ->assertSee('value="forecast"', false)
            ->assertSee('value="timeseries"', false)
            ->assertSee('Stock In', false)
            ->assertSee('Stock Out', false);
    }

    public function test_inventory_panel_uses_history_for_past_dates_not_current_stock(): void
    {
        $feeds = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sack',
            'current_stock' => 100,
            'min_level' => 20,
            'daily_stock' => 15,
        ]);

        $history = InventoryItemHistory::create([
            'inventory_item_id' => $feeds->id,
            'item_name' => 'Feeds',
            'action' => 'Restocked',
            'quantity_change' => 40,
            'stock_before' => 0,
            'stock_after' => 40,
            'note' => 'Recorded stock',
        ]);
        InventoryItemHistory::query()->where('id', $history->id)->update([
            'created_at' => '2026-11-01 08:00:00',
            'updated_at' => '2026-11-01 08:00:00',
        ]);

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard', [
                'dash_period' => 'date',
                'dash_date' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('Eggs', false)
            ->assertSee('Stock In', false)
            ->assertSee('40.00')
            ->assertSee('0.00')
            ->assertDontSee('100.00');
    }

    public function test_manager_revenue_and_dispatch_follow_a_past_date(): void
    {
        $past = Dispatch::create([
            'recipient' => 'North Store',
            'address' => 'Lipa City',
            'dispatch_date' => '2026-11-01',
            'price' => 80,
            'status' => 'Delivered',
        ]);
        DispatchItem::create([
            'dispatch_id' => $past->id,
            'size' => 'Large',
            'quantity' => 3,
        ]);
        Transaction::create([
            'dispatch_id' => $past->id,
            'customer' => 'North Store',
            'transaction_date' => '2026-11-01',
            'quantity' => 3,
            'price' => 80,
            'status' => 'Paid',
        ]);

        $today = Dispatch::create([
            'recipient' => 'South Store',
            'address' => 'Lipa City',
            'dispatch_date' => '2026-11-08',
            'price' => 40,
            'status' => 'Pending',
        ]);
        DispatchItem::create([
            'dispatch_id' => $today->id,
            'size' => 'Large',
            'quantity' => 9,
        ]);
        Transaction::create([
            'dispatch_id' => $today->id,
            'customer' => 'South Store',
            'transaction_date' => '2026-11-08',
            'quantity' => 9,
            'price' => 40,
            'status' => 'Paid',
        ]);

        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)
            ->get(route('manager.dashboard', [
                'dash_period' => 'date',
                'dash_date' => '2026-11-01',
            ]));

        $response->assertOk()->assertSee('₱80');
        $this->assertMatchesRegularExpression(
            '/stat-card--dispatch[\s\S]*?>\s*3\s*</',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/stat-card--revenue[\s\S]*?<h2>\s*₱80\s*<\/h2>/',
            $response->getContent()
        );
        $this->assertDoesNotMatchRegularExpression(
            '/stat-card--revenue[\s\S]*?<h2>\s*₱40\s*<\/h2>/',
            $response->getContent()
        );
    }

    public function test_picking_today_as_a_custom_date_keeps_today_labels_and_chart_controls(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'dash_period' => 'date',
                'dash_date' => '2026-11-08',
            ]))
            ->assertOk()
            ->assertSee("Welcome back! Here's today's poultry farm overview.")
            ->assertSee('Total Eggs Produced')
            ->assertSee('Eggs Stock')
            ->assertSee('value="2026-11-08"', false)
            ->assertSee('value="forecast"', false);
    }

    public function test_past_predicted_eggs_use_the_saved_forecast_for_the_next_day(): void
    {
        \App\Models\Forecast::query()->create([
            'forecast_date' => '2026-11-02',
            'predicted_eggs' => 3210,
            'series' => [],
        ]);

        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->get(route('manager.dashboard', [
                'dash_period' => 'date',
                'dash_date' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('<h2>3,210</h2>', false)
            ->assertSee('For Nov 2, 2026');
    }

    public function test_future_dates_are_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->from(route('owner.dashboard'))
            ->get(route('owner.dashboard', [
                'dash_period' => 'date',
                'dash_date' => '2026-11-09',
            ]))
            ->assertRedirect(route('owner.dashboard'))
            ->assertSessionHasErrors('dash_date');
    }

    public function test_today_keeps_chart_date_controls(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'trend_view' => 'historical',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-03',
            ]))
            ->assertOk()
            ->assertSee('value="historical" selected', false)
            ->assertSee('id="startDate"', false)
            ->assertSee('value="forecast"', false);
    }

    public function test_future_chart_dates_are_rejected_on_today(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->from(route('owner.dashboard'))
            ->get(route('owner.dashboard', [
                'trend_view' => 'historical',
                'start_date' => '2026-11-09',
                'end_date' => '2026-11-10',
            ]))
            ->assertRedirect(route('owner.dashboard'))
            ->assertSessionHasErrors('start_date');
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
                $series = [];
                foreach ($predictDates as $date) {
                    $series[] = [
                        'date' => $date,
                        'eggs' => 4000,
                        'sample_days' => 3,
                        'temperature' => 28.0,
                        'sizes' => [
                            'small' => 1000,
                            'medium' => 1000,
                            'large' => 1000,
                            'extra_large' => 1000,
                            'cracked' => 0,
                        ],
                    ];
                }

                return [
                    'predicted_eggs' => 4000,
                    'forecast_date' => $predictDates[0] ?? '2026-11-09',
                    'sample_days' => 3,
                    'temperature' => 28.0,
                    'model' => 'arima_2_1_1',
                    'series' => $series,
                    'error' => null,
                ];
            }
        });
    }

    private function harvest(string $date, int $eggs): void
    {
        Production::query()->create([
            'production_date' => $date,
            'batch_id' => 1,
            'small_eggs' => 0,
            'medium_eggs' => 0,
            'large_eggs' => $eggs,
            'extra_large_eggs' => 0,
            'cracked_eggs' => 0,
            'eggs_produced' => $eggs,
        ]);
    }
}
