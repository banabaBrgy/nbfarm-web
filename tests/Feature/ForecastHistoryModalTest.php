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
}
