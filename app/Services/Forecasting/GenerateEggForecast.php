<?php

namespace App\Services\Forecasting;

use App\Models\Forecast;
use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GenerateEggForecast
{
    public function __construct(private ForecastEngine $engine) {}

    /**
     * Next-day egg prediction (card). Uses stored row if the API is down.
     */
    public function predictedEggs(): int
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $result = $this->forWeek($weekStart, $weekStart->copy()->addDays(6));

        return (int) ($result['predicted_eggs'] ?? 0);
    }

    /**
     * Fit linear regression via Python and return the Mon–Sun series for the chart.
     *
     * @return array{predicted_eggs: int, labels: list<string>, data: list<int|null>}
     */
    public function forWeek(Carbon $weekStart, Carbon $weekEnd): array
    {
        $empty = [
            'predicted_eggs' => (int) (Forecast::latest('forecast_date')->value('predicted_eggs') ?? 0),
            'labels' => [],
            'data' => [],
        ];

        $cacheKey = 'egg-forecast:'.$weekStart->format('Y-m-d').':'.$weekEnd->format('Y-m-d');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($weekStart, $weekEnd, $empty) {
            $history = $this->dailyHistory();
            if (count($history) < 2) {
                return $empty;
            }

            $predictDates = [];
            $cursor = $weekStart->copy()->startOfDay();
            while ($cursor->lte($weekEnd)) {
                $predictDates[] = $cursor->format('Y-m-d');
                $cursor->addDay();
            }

            try {
                $payload = $this->engine->predict(
                    $history,
                    $predictDates,
                    (int) config('forecasting.window', 7)
                );
            } catch (Throwable $e) {
                Log::warning('Egg forecast generation failed', ['message' => $e->getMessage()]);

                return $empty;
            }

            $predicted = max(0, (int) ($payload['predicted_eggs'] ?? 0));
            $forecastDate = $payload['forecast_date'] ?? Carbon::tomorrow()->toDateString();

            $attributes = ['predicted_eggs' => $predicted];
            if (Schema::hasColumn('forecasts', 'series')) {
                $attributes['series'] = $payload['series'] ?? [];
            }

            Forecast::updateOrCreate(
                ['forecast_date' => $forecastDate],
                $attributes
            );

            return [
                'predicted_eggs' => $predicted,
                'labels' => $payload['labels'] ?? [],
                'data' => $payload['data'] ?? [],
            ];
        });
    }

    /**
     * @return list<array{date: string, eggs: int}>
     */
    private function dailyHistory(): array
    {
        $window = max(2, (int) config('forecasting.window', 7));

        $rows = Production::query()
            ->presentYear()
            ->select(
                DB::raw('DATE(production_date) as date'),
                DB::raw('SUM(eggs_produced) as total')
            )
            ->groupBy(DB::raw('DATE(production_date)'))
            ->orderByDesc(DB::raw('DATE(production_date)'))
            ->limit($window)
            ->get()
            ->sortBy('date')
            ->values();

        return $rows->map(fn ($row) => [
            'date' => Carbon::parse($row->date)->format('Y-m-d'),
            'eggs' => (int) $row->total,
        ])->all();
    }
}
