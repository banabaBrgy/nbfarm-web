<?php

namespace Tests\Feature;

use App\Models\Production;
use App\Models\QrTransaction;
use App\Services\EggProductionChart;
use App\Services\Forecasting\ForecastEngine;
use App\Services\MissedProductionFill;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MissedProductionFillTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_missed_past_days_are_filled_close_to_the_forecast(): void
    {
        Carbon::setTestNow('2026-11-08 09:00:00');
        $this->bindForecast(7200);

        $this->harvest('2026-11-01', 7000);
        $this->harvest('2026-11-03', 7100);

        $result = app(MissedProductionFill::class)->fill();

        $this->assertSame(5, $result['days']);
        $this->assertSame('2026-11-02', $result['start']);
        $this->assertSame('2026-11-07', $result['end']);

        $this->assertSame(7000, $this->totalOn('2026-11-01'));
        $this->assertSame(7100, $this->totalOn('2026-11-03'));
        $this->assertSame(0, $this->totalOn('2026-11-08'));

        foreach (['2026-11-02', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07'] as $date) {
            $rows = Production::query()->whereDate('production_date', $date)->get();
            $this->assertCount(3, $rows, $date);
            $total = (int) $rows->sum('eggs_produced');
            $this->assertLessThanOrEqual(30, abs($total - 7200), $date);
            $this->assertSame(0, $total % 30, $date);

            foreach ($rows as $row) {
                $sizeSum = (int) $row->small_eggs
                    + (int) $row->medium_eggs
                    + (int) $row->large_eggs
                    + (int) $row->extra_large_eggs;
                $this->assertSame((int) $row->eggs_produced, $sizeSum);
                $this->assertSame(
                    (int) $row->eggs_produced,
                    (int) QrTransaction::query()->where('production_id', $row->production_id)->sum('total_eggs')
                );
            }
        }

        $again = app(MissedProductionFill::class)->fill();
        $this->assertSame(0, $again['days']);

        $chart = app(EggProductionChart::class)->forRequest(Request::create('/dashboard', 'GET', [
            'trend_view' => 'comparison',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-07',
        ]));

        $actual = null;
        $forecast = null;
        foreach ($chart['datasets'] as $dataset) {
            if ($dataset['label'] === 'Actual Production') {
                $actual = $dataset['data'];
            }
            if ($dataset['label'] === 'Forecast') {
                $forecast = $dataset['data'];
            }
        }

        $this->assertIsArray($actual);
        $this->assertIsArray($forecast);
        $this->assertCount(7, $actual);

        foreach ($actual as $index => $eggs) {
            $this->assertNotNull($eggs);
            $this->assertNotNull($forecast[$index]);
        }

        foreach ([1, 3, 4, 5, 6] as $index) {
            $this->assertLessThanOrEqual(30, abs($actual[$index] - $forecast[$index]));
        }
    }

    public function test_align_year_pulls_far_2026_actuals_toward_the_forecast(): void
    {
        Carbon::setTestNow('2026-11-08 09:00:00');
        $this->bindForecast(7200);

        $this->harvest('2025-08-01', 5000);
        $this->harvest('2026-08-01', 6700);
        $this->harvest('2026-08-02', 7180);
        $this->harvest('2026-08-03', 300);

        $result = app(MissedProductionFill::class)->alignYear(2026);

        $this->assertSame(5000, $this->totalOn('2025-08-01'));
        $this->assertGreaterThan(0, $result['days']);
        $this->assertLessThanOrEqual(30, abs($this->totalOn('2026-08-01') - 7200));
        $this->assertLessThanOrEqual(30, abs($this->totalOn('2026-08-02') - 7200));
        $this->assertLessThanOrEqual(30, abs($this->totalOn('2026-08-03') - 7200));
        $this->assertSame(0, $this->totalOn('2026-11-08'));

        $again = app(MissedProductionFill::class)->alignYear(2026);
        $this->assertSame(0, $again['days']);
    }

    public function test_lower_high_days_only_rewrites_the_two_tallest_spikes(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->bindForecast(7200);

        $this->harvest('2026-10-13', 8100);
        $this->harvest('2026-10-19', 7800);
        $this->harvest('2026-10-14', 7100);
        $this->harvest('2026-10-20', 6900);

        $result = app(MissedProductionFill::class)->lowerHighDays(2);

        $this->assertSame(2, $result['days']);
        $this->assertSame(['2026-10-13', '2026-10-19'], $result['dates']);
        $this->assertLessThanOrEqual(30, abs($this->totalOn('2026-10-13') - 7200));
        $this->assertLessThanOrEqual(30, abs($this->totalOn('2026-10-19') - 7200));
        $this->assertSame(7100, $this->totalOn('2026-10-14'));
        $this->assertSame(6900, $this->totalOn('2026-10-20'));
    }

    private function bindForecast(int $eggs): void
    {
        $weather = $this->createMock(WeatherService::class);
        $weather->method('temperatureForDate')->willReturn(28.0);
        $this->app->instance(WeatherService::class, $weather);

        $this->app->instance(ForecastEngine::class, new class($eggs) implements ForecastEngine
        {
            public function __construct(private int $eggs) {}

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
                        'eggs' => $this->eggs,
                        'sample_days' => 3,
                        'temperature' => 28.0,
                        'sizes' => [
                            'small' => 1584,
                            'medium' => 2304,
                            'large' => 2016,
                            'extra_large' => 1296,
                            'cracked' => 0,
                        ],
                    ];
                }

                return [
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

    private function totalOn(string $date): int
    {
        return (int) Production::query()->whereDate('production_date', $date)->sum('eggs_produced');
    }
}
