<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    protected function fakeForecast(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-08-29T17:00',
                    'temperature_2m' => 31.4,
                    'relative_humidity_2m' => 72,
                    'weather_code' => 2,
                    'wind_speed_10m' => 8.2,
                ],
                'daily' => [
                    'time' => ['2026-08-29', '2026-08-30', '2026-08-31'],
                    'weather_code' => [2, 61, 0],
                    'temperature_2m_max' => [32.1, 30.0, 31.5],
                    'temperature_2m_min' => [24.2, 23.8, 24.0],
                    'precipitation_sum' => [0.0, 4.2, 0.1],
                ],
            ], 200),
        ]);
    }

    public function test_weather_service_maps_open_meteo_payload(): void
    {
        $this->fakeForecast();
        Cache::flush();

        $weather = app(WeatherService::class)->current();

        $this->assertIsArray($weather);
        $this->assertSame(31, $weather['temperature']);
        $this->assertSame('Partly cloudy', $weather['condition']);
        $this->assertSame('Brgy Sabang, San Jose, Batangas', $weather['location']);
        $this->assertSame('Sat, Aug 29, 2026', $weather['date_label']);
        $this->assertSame('29', $weather['forecast'][0]['day_num']);
        $this->assertCount(3, $weather['forecast']);
    }

    public function test_authenticated_users_can_fetch_weather_json(): void
    {
        $this->fakeForecast();
        Cache::flush();

        $user = new User([
            'username' => 'owner1',
            'role' => 'farm owner',
        ]);
        $user->id = 1;

        $this->actingAs($user)
            ->getJson(route('weather.current'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('temperature', 31)
            ->assertJsonPath('condition', 'Partly cloudy');
    }

    public function test_guests_cannot_fetch_weather_json(): void
    {
        $this->getJson(route('weather.current'))
            ->assertUnauthorized();
    }
}
