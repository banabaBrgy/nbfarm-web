<?php

namespace Tests\Unit;

use App\Models\Production;
use App\Services\Forecasting\ForecastEngine;
use App\Services\Forecasting\GenerateEggForecast;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EggForecastWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_python_engine_payload_for_summary(): void
    {
        Carbon::setTestNow('2026-10-04');

        $this->harvestDay('2025-10-05', 6000, 600, 1800, 3000, 600, 0);
        $this->harvestDay('2026-10-04', 6000, 750, 1950, 2700, 600, 0);

        $weather = $this->createMock(WeatherService::class);
        $weather->method('temperatureForDate')->willReturn(26.5);
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
                    'predicted_eggs' => 6600,
                    'forecast_date' => $predictDates[0] ?? '2026-10-05',
                    'sample_days' => 3,
                    'temperature' => 26.5,
                    'sizes' => [
                        'small' => 900,
                        'medium' => 2100,
                        'large' => 2700,
                        'extra_large' => 900,
                        'cracked' => 0,
                    ],
                    'series' => [[
                        'date' => $predictDates[0] ?? '2026-10-05',
                        'label' => 'Mon',
                        'eggs' => 6600,
                        'temperature' => 26.5,
                        'sample_days' => 3,
                        'sizes' => [
                            'small' => 900,
                            'medium' => 2100,
                            'large' => 2700,
                            'extra_large' => 900,
                            'cracked' => 0,
                        ],
                    ]],
                    'error' => null,
                ];
            }
        });

        $summary = app(GenerateEggForecast::class)->summary();

        $this->assertSame(6600, $summary['predicted_eggs']);
        $this->assertSame(3, $summary['sample_days']);
        $this->assertSame(26.5, $summary['temperature']);
        $this->assertSame(6600, collect($summary['sizes'])->sum('eggs'));
        $this->assertFalse(collect($summary['sizes'])->contains(fn ($size) => $size['key'] === 'cracked'));

        $previousSummary = app(GenerateEggForecast::class)->previousSummary();
        $this->assertSame('2026-10-03', $previousSummary['forecast_date']);
        $this->assertSame(6600, $previousSummary['predicted_eggs']);
        $this->assertSame(6600, collect($previousSummary['sizes'])->sum('eggs'));

        Carbon::setTestNow();
    }

    public function test_falls_back_to_local_model_when_python_fails(): void
    {
        Carbon::setTestNow('2026-10-04');

        $this->harvestDay('2023-10-05', 6000, 900, 2100, 2400, 600, 0);
        $this->harvestDay('2024-10-05', 6600, 900, 2100, 2700, 900, 0);
        $this->harvestDay('2025-10-05', 7200, 900, 2400, 3000, 900, 0);

        $weather = $this->createMock(WeatherService::class);
        $weather->method('temperatureForDate')->willReturn(28.0);
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
                throw new \RuntimeException('API down');
            }
        });

        $summary = app(GenerateEggForecast::class)->summary();

        $this->assertGreaterThan(0, $summary['predicted_eggs']);
        $this->assertSame(3, $summary['sample_days']);
        $this->assertSame(
            $summary['predicted_eggs'],
            collect($summary['sizes'])->sum('eggs')
        );
        $this->assertSame('2025-12-31', $summary['history_end']);
        $this->assertSame('2023-01-01', $summary['history_start']);

        Carbon::setTestNow();
    }

    public function test_rolling_window_in_2027_uses_2024_through_2026(): void
    {
        Carbon::setTestNow('2027-01-01');

        $this->harvestDay('2023-01-02', 6000, 900, 2100, 2400, 600, 0);
        $this->harvestDay('2024-01-02', 6600, 900, 2100, 2700, 900, 0);
        $this->harvestDay('2025-01-02', 7200, 900, 2400, 3000, 900, 0);
        $this->harvestDay('2026-01-02', 7800, 900, 2400, 3300, 1200, 0);

        $weather = $this->createMock(WeatherService::class);
        $weather->method('temperatureForDate')->willReturn(28.0);
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
                throw new \RuntimeException('API down');
            }
        });

        $summary = app(GenerateEggForecast::class)->summary();

        $this->assertSame(3, $summary['sample_days']);
        // Rolling 2024–2026 trend → 8400 for 2027-01-02 (2023 is dropped).
        $this->assertSame(8400, $summary['predicted_eggs']);
        $this->assertSame('2024-01-01', $summary['history_start']);
        $this->assertSame('2026-12-31', $summary['history_end']);

        Carbon::setTestNow();
    }

    public function test_live_forecast_applies_tomorrow_temperature_to_all_size_shifts(): void
    {
        Carbon::setTestNow('2026-10-04');

        $this->harvestDay('2023-10-05', 6000, 600, 1800, 3000, 600, 0);
        $this->harvestDay('2024-10-05', 6000, 600, 1800, 3000, 600, 0);
        $this->harvestDay('2025-10-05', 6000, 600, 1800, 3000, 600, 0);
        $this->harvestDay('2023-10-06', 6000, 600, 1800, 3000, 600, 0);
        $this->harvestDay('2024-10-06', 6000, 600, 1800, 3000, 600, 0);
        $this->harvestDay('2025-10-06', 6000, 600, 1800, 3000, 600, 0);

        $weather = $this->createMock(WeatherService::class);
        $weather->method('temperatureForDate')->willReturnCallback(
            fn ($date) => Carbon::parse($date)->toDateString() === '2026-10-05' ? 34.0 : 22.0
        );
        $this->app->instance(WeatherService::class, $weather);

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
                $this->captured->temperatures = $temperatures;
                $this->captured->options = $options;

                return [
                    'predicted_eggs' => 6000,
                    'forecast_date' => '2026-10-05',
                    'sample_days' => 3,
                    'temperature' => 34.0,
                    'model' => 'three_year_linear_regression',
                    'sizes' => [
                        'small' => 900,
                        'medium' => 1800,
                        'large' => 2700,
                        'extra_large' => 600,
                        'cracked' => 0,
                    ],
                    'series' => [
                        [
                            'date' => '2026-10-05',
                            'eggs' => 6000,
                            'temperature' => 34.0,
                            'sample_days' => 3,
                            'sizes' => [
                                'small' => 900,
                                'medium' => 1800,
                                'large' => 2700,
                                'extra_large' => 600,
                                'cracked' => 0,
                            ],
                        ],
                        [
                            'date' => '2026-10-06',
                            'eggs' => 6000,
                            'temperature' => 34.0,
                            'sample_days' => 3,
                            'sizes' => [
                                'small' => 900,
                                'medium' => 1800,
                                'large' => 2700,
                                'extra_large' => 600,
                                'cracked' => 0,
                            ],
                        ],
                    ],
                    'error' => null,
                ];
            }
        });

        app(GenerateEggForecast::class)->forWeek(
            Carbon::parse('2026-10-05'),
            Carbon::parse('2026-10-06')
        );

        $this->assertSame(34.0, $captured->temperatures['2026-10-05']);
        $this->assertSame(34.0, $captured->temperatures['2026-10-06']);
        $this->assertSame('2023-01-01', $captured->options['archive_start']);
        $this->assertSame('2025-12-31', $captured->options['archive_end']);

        Carbon::setTestNow();
    }

    public function test_rolling_window_in_2026_still_uses_2023_through_2025(): void
    {
        Carbon::setTestNow('2026-10-04');

        $this->harvestDay('2023-10-05', 6000, 900, 2100, 2400, 600, 0);
        $this->harvestDay('2024-10-05', 6600, 900, 2100, 2700, 900, 0);
        $this->harvestDay('2025-10-05', 7200, 900, 2400, 3000, 900, 0);

        $weather = $this->createMock(WeatherService::class);
        $weather->method('temperatureForDate')->willReturn(28.0);
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
                throw new \RuntimeException('API down');
            }
        });

        $summary = app(GenerateEggForecast::class)->summary();

        $this->assertSame('2023-01-01', $summary['history_start']);
        $this->assertSame('2025-12-31', $summary['history_end']);
        $this->assertSame(7800, $summary['predicted_eggs']);

        Carbon::setTestNow();
    }

    private function harvestDay(
        string $date,
        int $eggs,
        int $small,
        int $medium,
        int $large,
        int $xl,
        int $cracked
    ): void {
        Production::query()->create([
            'production_date' => $date,
            'batch_id' => 1,
            'small_eggs' => $small,
            'medium_eggs' => $medium,
            'large_eggs' => $large,
            'extra_large_eggs' => $xl,
            'cracked_eggs' => $cracked,
            'eggs_produced' => $eggs,
        ]);
    }
}
