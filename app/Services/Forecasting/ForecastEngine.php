<?php

namespace App\Services\Forecasting;

interface ForecastEngine
{
    /**
     * @param  list<array{date: string, eggs: int|float}>  $history
     * @param  list<string>  $predictDates  YYYY-MM-DD
     * @return array<string, mixed>
     */
    public function predict(array $history, array $predictDates, int $window): array;
}
