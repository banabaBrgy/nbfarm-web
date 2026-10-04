<?php

namespace App\Services\Forecasting;

interface ForecastEngine
{
    /**
     * @param  list<array<string, mixed>>  $history
     * @param  list<string>  $predictDates  YYYY-MM-DD
     * @param  array<string, float|int|null>  $temperatures  date => °C
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function predict(
        array $history,
        array $predictDates,
        int $window = 7,
        array $temperatures = [],
        array $options = [],
    ): array;
}
