<?php

namespace Tests\Feature;

use App\Models\Forecast;
use App\Models\Production;
use App\Models\User;
use App\Services\EggProductionChart;
use App\Services\Forecasting\ForecastEngine;
use App\Services\WeatherService;
use Illuminate\Http\Request;
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

    public function test_manager_dashboard_shows_pending_dispatches_instead_of_chart(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Pending Dispatches')
            ->assertSee('Inventory Levels')
            ->assertDontSee('id="trendView"', false)
            ->assertDontSee('value="forecast" selected', false);
    }

    public function test_historical_view_shows_past_production_and_arima_forecast(): void
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
            ->assertSee('"label":"Forecast"', false);

        $this->assertFalse($backtestCalled);
    }

    public function test_selecting_historical_opens_the_recent_range(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', ['trend_view' => 'historical']))
            ->assertOk()
            ->assertSee('value="2026-10-09"', false)
            ->assertSee('value="2026-11-07"', false)
            ->assertSee('id="eggChart"', false)
            ->assertSee('"label":"Forecast"', false);
    }

    public function test_selecting_historical_shows_the_forecast_when_one_exists(): void
    {
        foreach ([2023, 2024, 2025] as $year) {
            $this->harvest($year.'-11-01', 6900);
        }
        $this->harvest('2026-11-01', 7000);

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', ['trend_view' => 'historical']))
            ->assertOk()
            ->assertSee('id="eggChart"', false)
            ->assertSee('"label":"Forecast"', false)
            ->assertSee('"label":"Actual Production"', false)
            ->assertSee('7000', false)
            ->assertSee('4000', false)
            ->assertSee('borderDash: []', false)
            ->assertSee('hasForecastLine || data.datasets.length > 1', false);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', ['trend_view' => 'comparison']))
            ->assertOk()
            ->assertSee('id="eggChart"', false)
            ->assertSee('"label":"Forecast"', false)
            ->assertSee('4000', false);
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
            ->assertSee('4000', false);
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

    public function test_a_date_range_includes_a_forecast_for_every_day(): void
    {
        foreach ([2023, 2024, 2025] as $year) {
            $this->harvest($year.'-11-01', 6900);
            $this->harvest($year.'-11-02', 6900);
            $this->harvest($year.'-11-03', 6900);
        }
        $this->harvest('2026-11-01', 7000);

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'trend_view' => 'historical',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-03',
            ]))
            ->assertOk()
            ->assertSee('"label":"Actual Production"', false)
            ->assertSee('"label":"Forecast"', false)
            ->assertSee('[7000,null,null]', false)
            ->assertSee('[4000,4000,4000]', false)
            ->assertSee('spanGaps: isForecast', false)
            ->assertDontSee('No forecast could be made for these dates.', false);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'trend_view' => 'comparison',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-03',
            ]))
            ->assertOk()
            ->assertSee('"label":"Forecast"', false)
            ->assertSee('[4000,4000,4000]', false);
    }

    public function test_time_series_uses_the_model_totals(): void
    {
        foreach ([2023, 2024, 2025] as $year) {
            $this->harvest($year.'-11-09', 6000);
            $this->harvest($year.'-11-10', 9000);
            $this->harvest($year.'-11-11', 6000);
        }

        $chart = app(EggProductionChart::class)->forRequest(
            Request::create('/dashboard', 'GET', ['trend_view' => 'timeseries'])
        );

        $data = $chart['datasets'][0]['data'];

        $this->assertSame(4000, $data[0]);
        $this->assertSame(4000, $data[1]);
        $this->assertSame(4000, $data[2]);
    }

    public function test_comparison_shows_arima_spike_without_smoothing(): void
    {
        $this->app->instance(ForecastEngine::class, new class implements ForecastEngine
        {
            public function predict(
                array $history,
                array $predictDates,
                int $window = 7,
                array $temperatures = [],
                array $options = [],
            ): array {
                $values = [6800, 9000, 6800];
                $series = [];
                foreach (array_values($predictDates) as $index => $date) {
                    $eggs = $values[$index] ?? 6800;
                    $series[] = [
                        'date' => $date,
                        'eggs' => $eggs,
                        'sample_days' => 3,
                        'temperature' => 28.0,
                        'sizes' => [
                            'small' => 0,
                            'medium' => $eggs,
                            'large' => 0,
                            'extra_large' => 0,
                            'cracked' => 0,
                        ],
                    ];
                }

                return [
                    'predicted_eggs' => $series[0]['eggs'] ?? 0,
                    'forecast_date' => $predictDates[0] ?? '2026-11-01',
                    'sample_days' => 3,
                    'temperature' => 28.0,
                    'model' => 'arima_2_1_1',
                    'series' => $series,
                    'error' => null,
                ];
            }
        });

        $this->harvest('2026-11-01', 7000);
        $this->harvest('2026-11-02', 7100);
        $this->harvest('2026-11-03', 6900);

        $chart = app(EggProductionChart::class)->forRequest(
            Request::create('/dashboard', 'GET', [
                'trend_view' => 'comparison',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-03',
            ])
        );

        $forecast = collect($chart['datasets'])->firstWhere('label', 'Forecast');

        $this->assertNotNull($forecast);
        $this->assertSame([6800, 9000, 6800], $forecast['data']);
        $this->assertStringContainsString('seven-day-ahead', (string) $chart['notice']);
    }

    public function test_comparison_sends_history_through_today_not_the_range_end(): void
    {
        $captured = new \stdClass;
        $this->app->instance(ForecastEngine::class, new class($captured) implements ForecastEngine
        {
            public function __construct(private \stdClass $captured) {}

            public function predict(
                array $history,
                array $predictDates,
                int $window = 7,
                array $temperatures = [],
                array $options = [],
            ): array {
                $this->captured->options = $options;
                $this->captured->dates = $predictDates;
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
                    'forecast_date' => $predictDates[0] ?? '2026-11-01',
                    'sample_days' => 3,
                    'temperature' => 28.0,
                    'model' => 'arima_2_1_1',
                    'series' => $series,
                    'error' => null,
                ];
            }
        });

        $this->harvest('2026-11-01', 1800);
        $this->harvest('2026-11-08', 1900);

        app(EggProductionChart::class)->forRequest(
            Request::create('/dashboard', 'GET', [
                'trend_view' => 'comparison',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-07',
            ])
        );

        $this->assertSame('2026-11-08', $captured->options['archive_end'] ?? null);
        $this->assertSame(
            ['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07'],
            $captured->dates ?? null
        );
    }

    public function test_a_saved_forecast_far_from_the_model_does_not_spike_the_chart(): void
    {
        foreach ([2023, 2024, 2025] as $year) {
            $this->harvest($year.'-11-01', 6900);
        }

        Forecast::query()->create([
            'forecast_date' => '2026-11-01',
            'predicted_eggs' => 2000,
            'series' => [
                ['date' => '2026-11-01', 'eggs' => 2000],
            ],
        ]);

        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('owner.dashboard', [
                'trend_view' => 'comparison',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('[4000]', false)
            ->assertDontSee('[2000]', false);
    }

    public function test_a_saved_forecast_near_the_model_still_uses_the_live_total(): void
    {
        $this->harvest('2026-11-01', 1800);

        Forecast::query()->create([
            'forecast_date' => '2026-11-09',
            'predicted_eggs' => 3870,
            'series' => [
                ['date' => '2026-11-09', 'eggs' => 3870],
            ],
        ]);

        $chart = app(EggProductionChart::class)->forRequest(
            Request::create('/dashboard', 'GET', [
                'trend_view' => 'historical',
                'start_date' => '2026-11-09',
                'end_date' => '2026-11-09',
            ])
        );

        $this->assertSame([4000], $chart['datasets'][0]['data']);
    }

    public function test_historical_future_day_matches_seven_day_forecast(): void
    {
        $this->harvest('2026-11-01', 1800);

        Forecast::query()->create([
            'forecast_date' => '2026-11-09',
            'predicted_eggs' => 3899,
            'series' => [
                ['date' => '2026-11-09', 'eggs' => 3899],
                ['date' => '2026-11-10', 'eggs' => 3899],
            ],
        ]);

        $historical = app(EggProductionChart::class)->forRequest(
            Request::create('/dashboard', 'GET', [
                'trend_view' => 'historical',
                'start_date' => '2026-11-08',
                'end_date' => '2026-11-12',
            ])
        );
        $sevenDay = app(EggProductionChart::class)->forRequest(
            Request::create('/dashboard', 'GET', ['trend_view' => 'forecast'])
        );

        $historicalForecast = collect($historical['datasets'])->firstWhere('label', 'Forecast');
        $sevenDayForecast = collect($sevenDay['datasets'])->firstWhere('label', 'Forecast');

        $this->assertNotNull($historicalForecast);
        $this->assertNotNull($sevenDayForecast);
        $this->assertSame(4000, $sevenDayForecast['data'][0]);
        $this->assertSame(
            $sevenDayForecast['data'][0],
            $historicalForecast['data'][1],
            'Nov 9 live ARIMA should match Seven-Day Forecast hover'
        );
    }

    public function test_comparison_uses_the_arima_engine_for_the_forecast_line(): void
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
            ->assertSee('"label":"Actual Production"', false)
            ->assertSee('"label":"Forecast"', false)
            ->assertSee('1800', false)
            ->assertSee('4000', false);
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
