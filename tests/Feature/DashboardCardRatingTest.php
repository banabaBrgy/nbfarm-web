<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\Forecast;
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

class DashboardCardRatingTest extends TestCase
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

    public function test_production_rates_the_selected_day_against_its_forecast(): void
    {
        $this->forecast('2026-11-08', 1000);
        $this->harvest('2026-11-07', 400);
        $this->harvest('2026-11-08', 800);

        $this->actingAs($this->owner())
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertDontSee('-20.0%')
            ->assertDontSee('vs expected')
            ->assertDontSee('vs yesterday')
            ->assertDontSee('vs prior period');
    }

    public function test_production_has_no_rating_without_a_forecast_for_the_filter(): void
    {
        $this->harvest('2026-11-07', 500);
        $this->harvest('2026-11-08', 600);

        $this->actingAs($this->owner())
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertDontSee('+20.0%')
            ->assertDontSee('vs yesterday')
            ->assertDontSee('vs prior period');
    }

    public function test_date_filter_rates_that_date_not_today(): void
    {
        $this->forecast('2026-11-01', 1000);
        $this->forecast('2026-11-08', 1000);
        $this->harvest('2026-11-01', 800);
        $this->harvest('2026-11-08', 1200);

        $this->actingAs($this->owner())
            ->get(route('owner.dashboard', [
                'dash_period' => 'date',
                'dash_date' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertDontSee('-20.0%')
            ->assertDontSee('+20.0%')
            ->assertDontSee('vs prior period');
    }

    public function test_range_rates_the_filtered_dates_against_their_forecasts(): void
    {
        $this->harvest('2026-11-01', 100);
        $this->harvest('2026-11-02', 50);
        $this->forecast('2026-11-01', 200);
        $this->forecast('2026-11-02', 200);

        $this->actingAs($this->owner())
            ->get(route('owner.dashboard', [
                'dash_period' => 'range',
                'dash_from' => '2026-11-01',
                'dash_to' => '2026-11-02',
            ]))
            ->assertOk()
            ->assertDontSee('-62.5%')
            ->assertDontSee('vs expected')
            ->assertDontSee('vs prior period');
    }

    public function test_incomplete_range_forecast_does_not_use_the_prior_period(): void
    {
        $this->harvest('2026-10-30', 40);
        $this->harvest('2026-10-31', 40);
        $this->harvest('2026-11-01', 100);
        $this->harvest('2026-11-02', 50);
        $this->forecast('2026-11-01', 200);

        $this->actingAs($this->owner())
            ->get(route('owner.dashboard', [
                'dash_period' => 'range',
                'dash_from' => '2026-11-01',
                'dash_to' => '2026-11-02',
            ]))
            ->assertOk()
            ->assertDontSee('+87.5%')
            ->assertDontSee('vs prior period');
    }

    public function test_today_dispatch_and_sales_rate_against_yesterday_not_lifetime(): void
    {
        $this->dispatchDay('2026-11-01', 3, 80, 'Delivered');
        $this->dispatchDay('2026-11-07', 4, 100, 'Delivered');
        $this->dispatchDay('2026-11-08', 6, 80, 'Pending');

        $response = $this->actingAs($this->owner())
            ->get(route('owner.dashboard'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/stat-card--dispatch[\s\S]*?>\s*6\s*</',
            $response->getContent()
        );
        $response->assertDontSee('+50.0%')
            ->assertDontSee('-20.0%')
            ->assertDontSee('vs yesterday')
            ->assertDontSee('>13<', false);
    }

    public function test_inventory_levels_rate_stock_against_yesterday_and_min(): void
    {
        $feeds = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sack',
            'current_stock' => 100,
            'min_level' => 20,
            'daily_stock' => 15,
        ]);
        $this->ledger($feeds, 80, '2026-11-07 08:00:00');

        $trays = InventoryItem::create([
            'item_name' => 'Egg Trays',
            'unit' => 'pcs',
            'current_stock' => 2,
            'min_level' => 5,
            'daily_stock' => 1,
        ]);
        $this->ledger($trays, 4, '2026-11-07 08:00:00');

        $this->harvest('2026-11-07', 3000);
        $this->harvest('2026-11-08', 1500);

        $this->actingAs($this->owner())
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('+50.0%')
            ->assertSee('+6.3%')
            ->assertSee('-100.0%')
            ->assertDontSee('vs yesterday')
            ->assertSee('below min');
    }

    public function test_staff_dashboard_shows_production_and_inventory_ratings(): void
    {
        $this->forecast('2026-11-08', 1000);
        $this->harvest('2026-11-08', 800);
        $this->harvest('2026-11-07', 2000);

        $feeds = InventoryItem::create([
            'item_name' => 'Feeds',
            'unit' => 'sack',
            'current_stock' => 30,
            'min_level' => 20,
            'daily_stock' => 15,
        ]);
        $this->ledger($feeds, 20, '2026-11-07 08:00:00');

        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('-20.0%')
            ->assertSee('-25.0%')
            ->assertDontSee('vs expected')
            ->assertDontSee('vs yesterday');
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function forecast(string $date, int $eggs): void
    {
        Forecast::create([
            'forecast_date' => $date,
            'predicted_eggs' => $eggs,
        ]);
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

    private function dispatchDay(string $date, int $trays, float $price, string $status): void
    {
        $dispatch = Dispatch::create([
            'recipient' => 'Store '.$date,
            'address' => 'Lipa City',
            'dispatch_date' => $date,
            'price' => $price,
            'status' => $status,
        ]);
        DispatchItem::create([
            'dispatch_id' => $dispatch->id,
            'size' => 'Large',
            'quantity' => $trays,
        ]);
        Transaction::create([
            'customer' => 'Store '.$date,
            'transaction_date' => $date,
            'quantity' => $trays,
            'price' => $price,
            'status' => $status === 'Delivered' ? 'Paid' : 'Pending',
        ]);
    }

    private function ledger(InventoryItem $item, float $after, string $at): void
    {
        $history = InventoryItemHistory::create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->item_name,
            'action' => 'Restocked',
            'quantity_change' => $after,
            'stock_before' => 0,
            'stock_after' => $after,
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
}
