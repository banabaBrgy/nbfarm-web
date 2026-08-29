<?php

namespace App\Services;

use App\Models\Production;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class ForecastingService
{
    public static function latestPrediction()
    {
        // Latest production date
        $latestDate = Production::max('production_date');

        if (!$latestDate) {
            return 0;
        }


        // Get daily production totals for last 7 days
        $productions = Production::select(
                DB::raw('DATE(production_date) as date'),
                DB::raw('SUM(eggs_produced) as total')
            )
            ->whereBetween(
                'production_date',
                [
                    Carbon::parse($latestDate)
                        ->subDays(7),

                    Carbon::parse($latestDate)
                ]
            )
            ->groupBy(
                DB::raw('DATE(production_date)')
            )
            ->orderBy('date')
            ->get();


        if ($productions->count() < 2) {
            return 0;
        }


        // Linear Regression
        $x = [];
        $y = [];


        foreach ($productions as $index => $production) {

            $x[] = $index;
            $y[] = $production->total;

        }


        $n = count($x);


        $sumX = array_sum($x);
        $sumY = array_sum($y);


        $sumXY = 0;
        $sumXX = 0;


        for ($i = 0; $i < $n; $i++) {

            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];

        }


        $denominator =
            ($n * $sumXX) - ($sumX * $sumX);


        if ($denominator == 0) {
            return round(
                $sumY / $n
            );
        }


        $slope =
            (
                ($n * $sumXY)
                -
                ($sumX * $sumY)
            )
            /
            $denominator;


        $intercept =
            (
                $sumY
                -
                ($slope * $sumX)
            )
            /
            $n;



        // Next day forecast
        $nextX = $n;


        $prediction =
            ($slope * $nextX)
            +
            $intercept;


        return max(
            0,
            round($prediction)
        );
    }
}