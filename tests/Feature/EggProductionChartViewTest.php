<?php

namespace Tests\Feature;

use App\Models\Forecast;
use App\Models\Production;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EggProductionChartViewTest extends TestCase
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

    public function test_dashboard_opens_on_the_seven_day_view(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $response = $this->actingAs($owner)
            ->get(route('owner.dashboard'));

        $response->assertOk();
        $response->assertSee('Historical Production');
        $response->assertSee('Actual vs. Forecast');
        $response->assertSee('Time Series Forecast');
        $response->assertSee('Seven-Day Forecast');
        $response->assertSee('value="forecast" selected', false);
        $response->assertSee(
            '["Nov 9","Nov 10","Nov 11","Nov 12","Nov 13","Nov 14","Nov 15"]',
            false
        );
        $response->assertSee('"label":"Forecast"', false);
        $response->assertDontSee('"label":"Actual Production"', false);
    }

    public function test_manager_and_staff_dashboards_use_the_same_dropdown(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $sevenDays = '["Nov 9","Nov 10","Nov 11","Nov 12","Nov 13","Nov 14","Nov 15"]';

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('id="trendView"', false)
            ->assertSee('value="forecast" selected', false)
            ->assertSee($sevenDays, false);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('id="trendView"', false)
            ->assertSee('value="forecast" selected', false)
            ->assertSee($sevenDays, false);
    }

    public function test_historical_view_shows_past_production_only(): void
    {
        $this->harvest('2026-11-01', 1234);
        $backtestCalled = false;
        Http::fake(function ($request) use (&$backtestCalled) {
            if (str_contains($request->url(), '/backtest')) {
                $backtestCalled = true;
            }

            return Http::response(['series' => []], 200);
        });

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'trend_view' => 'historical',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-03',
            ]))
            ->assertOk()
            ->assertSee('value="historical" selected', false)
            ->assertSee('Historical Production', false)
            ->assertSee('"label":"Actual Production"', false)
            ->assertSee('1234', false)
            ->assertDontSee('"label":"Forecast"', false);

        $this->assertFalse($backtestCalled);
    }

    public function test_historical_view_asks_for_dates_before_drawing_a_chart(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', ['trend_view' => 'historical']))
            ->assertOk()
            ->assertSee('Choose a start and end date to see past production.')
            ->assertDontSee('id="eggChart"', false);
    }

    public function test_comparison_view_plots_actual_and_forecast_for_the_same_dates(): void
    {
        $this->harvest('2026-11-01', 1234);
        Forecast::query()->create([
            'forecast_date' => '2026-11-01',
            'predicted_eggs' => 2222,
            'series' => [
                ['date' => '2026-11-01', 'eggs' => 2222],
                ['date' => '2026-11-02', 'eggs' => 2300],
            ],
        ]);

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'trend_view' => 'comparison',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-02',
            ]))
            ->assertOk()
            ->assertSee('value="comparison" selected', false)
            ->assertSee('"label":"Actual Production"', false)
            ->assertSee('"label":"Forecast"', false)
            ->assertSee('1234', false)
            ->assertSee('2222', false);
    }

    public function test_saved_date_range_without_a_view_still_opens_the_comparison(): void
    {
        $this->harvest('2026-11-01', 1500);
        Forecast::query()->create([
            'forecast_date' => '2026-11-01',
            'predicted_eggs' => 1600,
            'series' => [
                ['date' => '2026-11-01', 'eggs' => 1600],
            ],
        ]);

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('value="comparison" selected', false)
            ->assertSee('"label":"Actual Production"', false)
            ->assertSee('"label":"Forecast"', false);
    }

    public function test_time_series_view_is_the_thirty_day_forecast_without_actuals(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', ['trend_view' => 'timeseries']))
            ->assertOk()
            ->assertSee('value="timeseries" selected', false)
            ->assertSee('Nov 9', false)
            ->assertSee('Dec 8', false)
            ->assertSee('Nov 24', false)
            ->assertSee('"label":"Forecast"', false)
            ->assertDontSee('"label":"Actual Production"', false)
            ->assertSee('style="display:none;"', false);
    }

    public function test_a_repeated_view_parameter_stays_on_the_seven_day_chart(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard').'?trend_view[]=historical')
            ->assertOk()
            ->assertSee('value="forecast" selected', false)
            ->assertSee(
                '["Nov 9","Nov 10","Nov 11","Nov 12","Nov 13","Nov 14","Nov 15"]',
                false
            );
    }

    public function test_past_views_reject_a_range_longer_than_a_year(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'trend_view' => 'historical',
                'start_date' => '2025-01-01',
                'end_date' => '2026-01-02',
            ]))
            ->assertOk()
            ->assertSee('Choose a range of 366 days or fewer.')
            ->assertDontSee('id="eggChart"', false);
    }

    public function test_comparison_without_forecast_values_keeps_the_actual_line_only(): void
    {
        $this->harvest('2026-11-01', 1800);

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'trend_view' => 'comparison',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('No forecast was saved for these dates.', false)
            ->assertSee('"label":"Actual Production"', false)
            ->assertDontSee('"label":"Forecast"', false);
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
