<?php

namespace App\Services;

use App\Services\Forecasting\GenerateEggForecast;

class ForecastingService
{
    /**
     * Next-day egg forecast from the Python ARIMA(2,1,1) service.
     */
    public static function latestPrediction(): int
    {
        return app(GenerateEggForecast::class)->predictedEggs();
    }
}
