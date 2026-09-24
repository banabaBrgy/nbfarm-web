<?php

namespace Tests\Unit;

use App\Models\Production;
use App\Services\Forecasting\ForecastEngine;
use App\Services\Forecasting\GenerateEggForecast;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EggForecastWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_yesterday_with_no_eggs_predicts_zero_and_ignores_older_harvest(): void
    {
        Carbon::setTestNow('2026-09-24');

        $this->harvest('2026-08-01', 4000);

        $box = new \stdClass;
        $box->history = null;
        $this->app->instance(ForecastEngine::class, new class($box) implements ForecastEngine
        {
            public function __construct(private \stdClass $box) {}

            public function predict(array $history, array $predictDates, int $window): array
            {
                $this->box->history = $history;

                return [
                    'predicted_eggs' => 4000,
                    'forecast_date' => '2026-09-24',
                    'labels' => [],
                    'data' => [],
                    'series' => [],
                ];
            }
        });

        $result = app(GenerateEggForecast::class)->predictedEggs();

        $this->assertSame(0, $result);
        $this->assertSame('2026-09-17', $box->history[0]['date']);
        $this->assertSame('2026-09-23', $box->history[6]['date']);
        $this->assertSame(0, $box->history[6]['eggs']);
        $this->assertNotContains('2026-08-01', array_column($box->history, 'date'));

        Carbon::setTestNow();
    }

    public function test_history_is_the_last_seven_calendar_days_ending_yesterday(): void
    {
        Carbon::setTestNow('2026-09-24');

        $this->harvest('2026-08-20', 9000);
        $this->harvest('2026-09-17', 1000);
        $this->harvest('2026-09-23', 1700);

        $box = new \stdClass;
        $box->history = null;
        $this->app->instance(ForecastEngine::class, new class($box) implements ForecastEngine
        {
            public function __construct(private \stdClass $box) {}

            public function predict(array $history, array $predictDates, int $window): array
            {
                $this->box->history = $history;

                return [
                    'predicted_eggs' => 1800,
                    'forecast_date' => '2026-09-24',
                    'labels' => ['Thu'],
                    'data' => [1800],
                    'series' => [],
                ];
            }
        });

        $result = app(GenerateEggForecast::class)->predictedEggs();

        $seen = $box->history;
        $this->assertSame(1800, $result);
        $this->assertCount(7, $seen);
        $this->assertSame('2026-09-17', $seen[0]['date']);
        $this->assertSame(1000, $seen[0]['eggs']);
        $this->assertSame('2026-09-18', $seen[1]['date']);
        $this->assertSame(0, $seen[1]['eggs']);
        $this->assertSame('2026-09-23', $seen[6]['date']);
        $this->assertSame(1700, $seen[6]['eggs']);
        $this->assertNotContains('2026-08-20', array_column($seen, 'date'));

        Carbon::setTestNow();
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
