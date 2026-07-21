<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Forecast;

class ForecastingService
{
    public function generateDailyForecast()
    {
        /*
        |--------------------------------------------------------------------------
        | GET DAILY TOTAL PRODUCTION
        |--------------------------------------------------------------------------
        | Instead of forecasting every batch separately,
        | pagsasamahin muna ang Batch 1,2,3 bawat araw.
        |
        */

        $records = Production::selectRaw('production_date, SUM(eggs_produced) as eggs_produced')
            ->groupBy('production_date')
            ->orderBy('production_date')
            ->get();

        if ($records->count() < 2) {
            return 0;
        }

        $n = $records->count();

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        foreach ($records as $index => $row) {

            $x = $index + 1;
            $y = $row->eggs_produced;

            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
        }

        $b = (($n * $sumXY) - ($sumX * $sumY))
            /
            (($n * $sumXX) - ($sumX * $sumX));

        $a = ($sumY - ($b * $sumX)) / $n;

        $nextX = $n + 1;

        $forecast = round($a + ($b * $nextX));

        Forecast::updateOrCreate(

            [
                'forecast_date' => now()->addDay()->toDateString(),
            ],

            [
                'predicted_eggs' => $forecast,
            ]

        );

        return $forecast;
    }
}