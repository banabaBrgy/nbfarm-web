<?php

namespace App\Services\Forecasting;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PythonForecastEngine implements ForecastEngine
{
    public function predict(array $history, array $predictDates, int $window): array
    {
        $base = config('forecasting.api_url');
        if ($base === '') {
            throw new RuntimeException('FORECAST_API_URL is not set.');
        }

        $key = (string) config('forecasting.api_key');
        $body = [
            'history' => $history,
            'predict_dates' => $predictDates,
            'window' => $window,
        ];

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
