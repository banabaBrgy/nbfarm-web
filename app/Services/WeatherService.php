<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    public function current(): ?array
    {
        $lat = config('services.weather.lat');
        $lon = config('services.weather.lon');

        if ($lat === null || $lat === '' || $lon === null || $lon === '') {
            return null;
        }

        $cacheKey = sprintf('farm_weather:v2:%s:%s', $lat, $lon);

        try {
            return Cache::remember($cacheKey, 300, function () use ($lat, $lon) {
                return $this->fetch((float) $lat, (float) $lon);
            });
        } catch (\Throwable $e) {
            Log::warning('Weather fetch failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function fetch(float $lat, float $lon): array
    {
        $response = Http::timeout(8)->get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => $lat,
            'longitude' => $lon,
            'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum',
            'timezone' => 'Asia/Manila',
            'forecast_days' => 5,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Open-Meteo HTTP '.$response->status());
        }

        $payload = $response->json();
        $current = $payload['current'] ?? null;
        $daily = $payload['daily'] ?? null;

        if (! is_array($current) || ! is_array($daily)) {
            throw new \RuntimeException('Open-Meteo returned an unexpected payload.');
        }

        $code = (int) ($current['weather_code'] ?? 0);
        $condition = $this->condition($code);
        $updatedAt = Carbon::parse($current['time'] ?? now(), 'Asia/Manila');

        $forecast = [];
        $days = $daily['time'] ?? [];

        foreach ($days as $index => $date) {
            $dayCode = (int) ($daily['weather_code'][$index] ?? 0);
            $dayCondition = $this->condition($dayCode);
            $isToday = Carbon::parse($date, 'Asia/Manila')->isSameDay($updatedAt);

            $forecast[] = [
                'date' => $date,
                'day' => $isToday ? 'Today' : Carbon::parse($date, 'Asia/Manila')->format('D'),
                'day_num' => Carbon::parse($date, 'Asia/Manila')->format('j'),
                'max' => (int) round((float) ($daily['temperature_2m_max'][$index] ?? 0)),
                'min' => (int) round((float) ($daily['temperature_2m_min'][$index] ?? 0)),
                'precip' => round((float) ($daily['precipitation_sum'][$index] ?? 0), 1),
                'icon' => $dayCondition['icon'],
                'modifier' => $dayCondition['modifier'],
                'condition' => $dayCondition['label'],
            ];
        }

        return [
            'location' => (string) config('services.weather.location', 'Farm'),
            'temperature' => (int) round((float) ($current['temperature_2m'] ?? 0)),
            'humidity' => (int) round((float) ($current['relative_humidity_2m'] ?? 0)),
            'wind' => (int) round((float) ($current['wind_speed_10m'] ?? 0)),
            'condition' => $condition['label'],
            'icon' => $condition['icon'],
            'modifier' => $condition['modifier'],
            'date_label' => $updatedAt->format('D, M j, Y'),
            'updated_at' => $updatedAt->toIso8601String(),
            'forecast' => $forecast,
        ];
    }

    /**
     * @return array{label: string, icon: string, modifier: string}
     */
    private function condition(int $code): array
    {
        return match (true) {
            $code === 0 => ['label' => 'Clear', 'icon' => 'fa-sun', 'modifier' => 'sun'],
            $code <= 2 => ['label' => 'Partly cloudy', 'icon' => 'fa-cloud-sun', 'modifier' => 'sun'],
            $code === 3 => ['label' => 'Overcast', 'icon' => 'fa-cloud', 'modifier' => 'cloud'],
            $code <= 48 => ['label' => 'Foggy', 'icon' => 'fa-smog', 'modifier' => 'fog'],
            $code <= 57 => ['label' => 'Drizzle', 'icon' => 'fa-cloud-rain', 'modifier' => 'rain'],
            $code <= 67 => ['label' => 'Rain', 'icon' => 'fa-cloud-showers-heavy', 'modifier' => 'rain'],
            $code <= 77 => ['label' => 'Snow', 'icon' => 'fa-snowflake', 'modifier' => 'cloud'],
            $code <= 82 => ['label' => 'Showers', 'icon' => 'fa-cloud-showers-heavy', 'modifier' => 'rain'],
            $code <= 86 => ['label' => 'Snow showers', 'icon' => 'fa-snowflake', 'modifier' => 'cloud'],
            default => ['label' => 'Thunderstorm', 'icon' => 'fa-cloud-bolt', 'modifier' => 'storm'],
        };
    }
}
