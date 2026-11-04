<?php

namespace Tests\Unit;

use App\Models\Production;
use App\Services\Forecasting\ForecastEngine;
use App\Services\ProductionYearTrend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionYearTrendTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_labels_are_january_through_december(): void
    {
        $trend = app(ProductionYearTrend::class);

        $this->assertSame(
            ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            $trend->forYear(null)['months']
        );
    }

    public function test_selected_year_returns_actual_and_forecast_series(): void
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
                $series = [];
                foreach ($predictDates as $date) {
                    $series[] = [
                        'date' => $date,
                        'eggs' => 3000,
                        'sample_days' => 1,
                        'temperature' => 28.0,
                        'sizes' => [
                            'small' => 300,
                            'medium' => 900,
                            'large' => 900,
                            'extra_large' => 900,
                            'cracked' => 0,
                        ],
                    ];
                }

                return [
                    'predicted_eggs' => 3000,
                    'forecast_date' => $predictDates[0] ?? '2025-01-01',
                    'sample_days' => 1,
                    'temperature' => 28.0,
                    'series' => $series,
                    'error' => null,
                ];
            }
        });

        Production::withoutEvents(function () {
            Production::create([
                'production_date' => '2025-01-15',
                'batch_id' => 1,
                'small_eggs' => 1000,
                'medium_eggs' => 1000,
                'large_eggs' => 1000,
                'extra_large_eggs' => 500,
                'cracked_eggs' => 0,
                'eggs_produced' => 3500,
            ]);
            Production::create([
                'production_date' => '2025-02-10',
                'batch_id' => 1,
                'small_eggs' => 800,
                'medium_eggs' => 800,
                'large_eggs' => 800,
                'extra_large_eggs' => 400,
                'cracked_eggs' => 0,
                'eggs_produced' => 2800,
            ]);
        });

        $trend = app(ProductionYearTrend::class)->forYear(2025);

        $this->assertSame(2025, $trend['selectedYear']);
        $this->assertCount(2, $trend['series']);
        $this->assertSame('Actual', $trend['series'][0]['label']);
        $this->assertSame('Forecast', $trend['series'][1]['label']);
        $this->assertSame('forecast', $trend['series'][1]['style']);

        $this->assertSame(3500, $trend['series'][0]['data'][0]);
        $this->assertSame(2800, $trend['series'][0]['data'][1]);
        $this->assertNull($trend['series'][0]['data'][2]);

        $this->assertSame(3000, $trend['series'][1]['data'][0]);
        $this->assertSame(3000, $trend['series'][1]['data'][1]);
        $this->assertNull($trend['series'][1]['data'][2]);
    }

    public function test_year_2023_returns_actual_and_within_year_forecast(): void
    {
        Production::withoutEvents(function () {
            Production::create([
                'production_date' => '2023-01-10',
                'batch_id' => 1,
                'small_eggs' => 1000,
                'medium_eggs' => 1000,
                'large_eggs' => 1000,
                'extra_large_eggs' => 0,
                'cracked_eggs' => 0,
                'eggs_produced' => 3000,
            ]);
            Production::create([
                'production_date' => '2023-01-11',
                'batch_id' => 1,
                'small_eggs' => 1100,
                'medium_eggs' => 1100,
                'large_eggs' => 1100,
                'extra_large_eggs' => 0,
                'cracked_eggs' => 0,
                'eggs_produced' => 3300,
            ]);
            Production::create([
                'production_date' => '2023-02-05',
                'batch_id' => 1,
                'small_eggs' => 900,
                'medium_eggs' => 900,
                'large_eggs' => 900,
                'extra_large_eggs' => 0,
                'cracked_eggs' => 0,
                'eggs_produced' => 2700,
            ]);
        });

        $trend = app(ProductionYearTrend::class)->forYear(2023);

        $this->assertSame(2023, $trend['selectedYear']);
        $this->assertSame('Actual', $trend['series'][0]['label']);
        $this->assertSame('Forecast', $trend['series'][1]['label']);

        // Jan actual = 3000 + 3300; first day forecast is 0 (no prior days).
        $this->assertSame(6300, $trend['series'][0]['data'][0]);
        $this->assertSame(3000, $trend['series'][1]['data'][0]);

        // Feb forecast averages Jan days (3000, 3300) → 3150 → nearest tray 3150.
        $this->assertSame(2700, $trend['series'][0]['data'][1]);
        $this->assertSame(3150, $trend['series'][1]['data'][1]);
    }
}
