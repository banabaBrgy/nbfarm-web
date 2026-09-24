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

        $cacheKey = 'egg-forecast:v2:'.$weekStart->format('Y-m-d').':'.$weekEnd->format('Y-m-d');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($weekStart, $weekEnd, $empty) {
            $history = $this->dailyHistory();
            $yesterdayEggs = (int) ($history[array_key_last($history)]['eggs'] ?? 0);
            if ($yesterdayEggs <= 0) {
                $empty['predicted_eggs'] = 0;
            }

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

            $predicted = $yesterdayEggs > 0
                ? max(0, (int) ($payload['predicted_eggs'] ?? 0))
                : 0;
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
     * Recompute every saved forecast with the current 7-day rule.
     * Dates stay as they are. A day before the forecast with no eggs becomes 0.
     *
     * @return list<array{date: string, from: int, to: int}>
     */
    public function correctStoredHistory(): array
    {
        $changes = [];

        Forecast::query()->orderBy('forecast_date')->each(function (Forecast $forecast) use (&$changes) {
            $date = Carbon::parse($forecast->forecast_date)->startOfDay();
            $from = (int) $forecast->predicted_eggs;
            $to = $this->predictedFor($date);

            if ($from !== $to) {
                $forecast->predicted_eggs = $to;
                $forecast->save();
            }

            $changes[] = [
                'date' => $date->toDateString(),
                'from' => $from,
                'to' => $to,
            ];
        });

        return $changes;
    }

    /**
     * Forecast for one date from the 7 calendar days before it.
     */
    public function predictedFor(Carbon $forecastDate): int
    {
        $history = $this->historyEnding($forecastDate->copy()->subDay()->startOfDay());
        $yesterdayEggs = (int) ($history[array_key_last($history)]['eggs'] ?? 0);
        if ($yesterdayEggs <= 0) {
            return 0;
        }

        $payload = $this->engine->predict(
            $history,
            [$forecastDate->toDateString()],
            count($history)
        );

        return max(0, (int) ($payload['predicted_eggs'] ?? 0));
    }

    /**
     * Latest 7 calendar days ending yesterday. A day with no harvest is 0.
     *
     * @return list<array{date: string, eggs: int}>
     */
    private function dailyHistory(): array
    {
        return $this->historyEnding(Carbon::yesterday()->startOfDay());
    }

    /**
     * @return list<array{date: string, eggs: int}>
     */
    private function historyEnding(Carbon $end): array
    {
        $window = max(2, (int) config('forecasting.window', 7));
        $end = $end->copy()->startOfDay();
        $start = $end->copy()->subDays($window - 1);

        $totals = Production::query()
            ->whereBetween('production_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->select(
                DB::raw('DATE(production_date) as date'),
                DB::raw('SUM(eggs_produced) as total')
            )
            ->groupBy(DB::raw('DATE(production_date)'))
            ->get();

        $byDate = [];
        foreach ($totals as $row) {
            $byDate[Carbon::parse($row->date)->toDateString()] = (int) $row->total;
        }

        $history = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $history[] = [
                'date' => $key,
                'eggs' => $byDate[$key] ?? 0,
            ];
            $cursor->addDay();
        }

        return $history;
    }
}
