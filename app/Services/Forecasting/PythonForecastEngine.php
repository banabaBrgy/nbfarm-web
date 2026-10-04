<?php

namespace App\Services\Forecasting;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PythonForecastEngine implements ForecastEngine
{
    public function predict(
        array $history,
        array $predictDates,
        int $window = 7,
        array $temperatures = [],
        array $options = [],
    ): array {
        $base = config('forecasting.api_url');
        if ($base === '') {
            throw new RuntimeException('FORECAST_API_URL is not set.');
        }

        $key = (string) config('forecasting.api_key');
        $body = array_merge([
            'history' => $history,
            'predict_dates' => $predictDates,
            'window' => $window,
            'temperatures' => $temperatures,
            'model' => 'three_year_linear_regression',
            'history_years' => (int) config('forecasting.history_years', 3),
            'neutral_temperature' => (float) config('forecasting.neutral_temperature', 28),
            'temp_shift_per_degree' => (float) config('forecasting.temp_shift_per_degree', 0.015),
            'temp_shift_cap' => (float) config('forecasting.temp_shift_cap', 0.20),
            'eggs_per_tray' => 30,
        ], $options);

        $response = null;
        foreach (['/forecast', '/api/forecast'] as $path) {
            $request = Http::timeout((int) config('forecasting.timeout', 10))
                ->acceptJson();

            if ($key !== '') {
                $request = $request->withHeaders(['X-Forecast-Key' => $key]);
            }

            $response = $request->post($base.$path, $body);
            if (! $response->notFound()) {
                break;
            }
        }

        if ($response === null || $response->failed()) {
            Log::warning('Forecast API failed', [
                'status' => $response?->status(),
                'body' => $response?->body(),
            ]);
            throw new RuntimeException('Forecast API request failed (HTTP '.($response?->status() ?? 0).').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Forecast API returned invalid JSON.');
        }

        if (! empty($payload['error'])) {
            throw new RuntimeException((string) $payload['error']);
        }

        return $payload;
    }
}
