<?php

namespace App\Services;

use App\Services\Forecasting\GenerateEggForecast;

class ForecastingService
{
    /**
     * Next-day egg forecast from the Python linear-regression API.
     */
    public static function latestPrediction(): int
    {
        return app(GenerateEggForecast::class)->predictedEggs();
    }
}
