<?php

namespace Tests\Feature;

use App\Models\Forecast;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDateForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_predicted_eggs_follow_the_day_after_the_selected_production_date(): void
    {
        Carbon::setTestNow('2026-10-31');

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
                        'eggs' => 9999,
                        'sample_days' => 1,
                        'temperature' => 28.0,
                        'sizes' => [
                            'small' => 999,
                            'medium' => 3000,
                            'large' => 3000,
                            'extra_large' => 3000,
                            'cracked' => 0,
                        ],
                    ];
                }

                return [
                    'predicted_eggs' => 9999,
                    'forecast_date' => $predictDates[0] ?? '2026-11-01',
                    'sample_days' => 1,
                    'temperature' => 28.0,
                    'series' => $series,
                    'error' => null,
                ];
            }
        });

        Forecast::query()->create([
            'forecast_date' => '2026-10-30',
            'predicted_eggs' => 7100,
        ]);
        Forecast::query()->create([
            'forecast_date' => '2026-10-31',
            'predicted_eggs' => 7320,
        ]);
        Forecast::query()->create([
            'forecast_date' => '2026-11-01',
            'predicted_eggs' => 7200,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/staff/production?date=2026-10-30')
            ->assertOk()
            ->assertSee('Next-day forecast for Oct 31', false)
            ->assertSee('7,320', false)
            ->assertSee('For Oct 31, 2026', false)
            ->assertSee('For Oct 30, 2026', false)
            ->assertSee('7,100', false)
            ->assertDontSee('9,999', false);

        $this->assertSame(7320, (int) Forecast::query()->whereDate('forecast_date', '2026-10-31')->value('predicted_eggs'));
        $this->assertSame(7200, (int) Forecast::query()->whereDate('forecast_date', '2026-11-01')->value('predicted_eggs'));

        $this->actingAs($user)
            ->get('/staff/production?date=2026-10-31')
            ->assertOk()
            ->assertSee('Next-day forecast for Nov 1', false)
            ->assertSee('9,999', false);

        Carbon::setTestNow();
    }
}
