<?php

namespace Tests\Feature;

use App\Models\Forecast;
use App\Models\User;
use App\Services\Forecasting\ForecastEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastHistoryModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_page_includes_forecast_history_modal(): void
    {
        $this->app->instance(ForecastEngine::class, new class implements ForecastEngine
        {
            public function predict(array $history, array $predictDates, int $window): array
            {
                return [
                    'predicted_eggs' => 0,
                    'forecast_date' => '2026-09-24',
                    'labels' => [],
                    'data' => [],
                    'series' => [],
                ];
            }
        });

        Forecast::query()->create([
            'forecast_date' => '2026-09-21',
            'predicted_eggs' => 1300,
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/staff/production');

        $response->assertOk();
        $response->assertSee('id="forecastHistoryModal"', false);
        $response->assertSee('View forecast history', false);
        $response->assertSee('Sep 21, 2026', false);
        $response->assertSee('1,300', false);
    }

    public function test_forecast_history_stays_open_on_every_page(): void
    {
        $this->app->instance(ForecastEngine::class, new class implements ForecastEngine
        {
            public function predict(array $history, array $predictDates, int $window): array
            {
                return [
                    'predicted_eggs' => 0,
                    'forecast_date' => '2026-09-24',
                    'labels' => [],
                    'data' => [],
                    'series' => [],
                ];
            }
        });

        foreach (range(1, 6) as $day) {
            Forecast::query()->create([
                'forecast_date' => sprintf('2026-08-%02d', $day),
                'predicted_eggs' => 1000 + $day,
            ]);
        }

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/staff/production')
            ->assertOk()
            ->assertSee('forecast_open=1', false)
            ->assertDontSee('getOrCreateInstance(modal).show()', false);

        $this->actingAs($user)
            ->get('/staff/production?forecast_open=1&forecast_page=1')
            ->assertOk()
            ->assertSee('getOrCreateInstance(modal).show()', false);
    }
}
