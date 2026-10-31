<?php

namespace App\Services\Forecasting;

use App\Models\Forecast;
use App\Models\Production;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GenerateEggForecast
{
    public function __construct(
        private WeatherService $weather,
        private ForecastEngine $engine,
    ) {}

    /**
     * Next-day egg prediction (card).
     */
    public function predictedEggs(): int
    {
        return (int) ($this->summary()['predicted_eggs'] ?? 0);
    }

    /**
     * Card total plus a size summary for the forecast modal.
     * Total: linear regression on the same calendar day across up to 3 years.
     * Sizes: that historical mix, shifted by tomorrow's temperature.
     *
     * @return array{
     *     predicted_eggs: int,
     *     forecast_date: string,
     *     temperature: float|null,
     *     history_years: int,
     *     history_start: string,
     *     history_end: string,
     *     sample_days: int,
     *     engine: string,
     *     sizes: list<array{key: string, label: string, eggs: int, trays: int, share: float}>
     * }
     */
    public function summary(): array
    {
        $forecastDate = Carbon::tomorrow()->startOfDay();
        $summary = $this->forecastSummaryForDate($forecastDate);

        $this->persistForecast($forecastDate->toDateString(), $summary['predicted_eggs'], [
            [
                'date' => $forecastDate->toDateString(),
                'label' => $forecastDate->format('D'),
                'eggs' => $summary['predicted_eggs'],
            ],
        ]);

        return $summary;
    }

    /**
     * Forecast for the day before the next-day forecast (today).
     *
     * @return array{
     *     predicted_eggs: int,
     *     forecast_date: string,
     *     temperature: float|null,
     *     history_years: int,
     *     history_start: string,
     *     history_end: string,
     *     sample_days: int,
     *     engine: string,
     *     sizes: list<array{key: string, label: string, eggs: int, trays: int, share: float}>
     * }
     */
    public function previousSummary(): array
    {
        return $this->forecastSummaryForDate(Carbon::tomorrow()->subDay()->startOfDay());
    }

    /**
     * Next-day forecast for the production date on screen.
     * October 30 loads the forecast saved for October 31.
     *
     * @return array{
     *     predicted_eggs: int,
     *     forecast_date: string,
     *     temperature: float|null,
     *     history_years: int,
     *     history_start: string,
     *     history_end: string,
     *     sample_days: int,
     *     engine: string,
     *     sizes: list<array{key: string, label: string, eggs: int, trays: int, share: float}>
     * }
     */
    public function summaryForProductionDate(Carbon $productionDate): array
    {
        $forecastDate = $productionDate->copy()->startOfDay()->addDay();

        if ($forecastDate->equalTo(Carbon::tomorrow()->startOfDay())) {
            return $this->summary();
        }

        return $this->savedSummary($forecastDate);
    }

    /**
     * Forecast for the production date itself, the day before the card's next-day forecast.
     *
     * @return array{
     *     predicted_eggs: int,
     *     forecast_date: string,
     *     temperature: float|null,
     *     history_years: int,
     *     history_start: string,
     *     history_end: string,
     *     sample_days: int,
     *     engine: string,
     *     sizes: list<array{key: string, label: string, eggs: int, trays: int, share: float}>
     * }
     */
    public function previousSummaryForProductionDate(Carbon $productionDate): array
    {
        $forecastDate = $productionDate->copy()->startOfDay();

        if ($forecastDate->equalTo(Carbon::today()->startOfDay())) {
            return $this->previousSummary();
        }

        return $this->savedSummary($forecastDate);
    }

    /**
     * Egg totals for the next N days starting tomorrow.
     * Read-only: does not write forecast rows. The saved row for a date
     * stays the expected number the production-drop alert compares later.
     *
     * @return list<array{date: string, eggs: int}>
     */
    public function upcomingDays(int $days = 7): array
    {
        $days = max(1, $days);
        $dates = [];
        $cursor = Carbon::tomorrow()->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        try {
            $byDate = $this->predictDates($dates);
        } catch (Throwable $e) {
            Log::warning('Upcoming egg forecast failed', ['message' => $e->getMessage()]);
            $byDate = [];
        }

        $rows = [];
        foreach ($dates as $date) {
            $rows[] = [
                'date' => $date,
                'eggs' => (int) ($byDate[$date]['eggs'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     predicted_eggs: int,
     *     forecast_date: string,
     *     temperature: float|null,
     *     history_years: int,
     *     history_start: string,
     *     history_end: string,
     *     sample_days: int,
     *     engine: string,
     *     sizes: list<array{key: string, label: string, eggs: int, trays: int, share: float}>
     * }
     */
    private function forecastSummaryForDate(Carbon $forecastDate): array
    {
        $forecastDate = $forecastDate->copy()->startOfDay();
        $prediction = $this->predictForDate($forecastDate);
        $archive = $this->archiveBounds($forecastDate);

        $labels = [
            'small' => 'Small',
            'medium' => 'Medium',
            'large' => 'Large',
            'extra_large' => 'Extra Large',
        ];

        $predicted = (int) $prediction['eggs'];
        $sizes = [];
        foreach ($labels as $key => $label) {
            $eggs = (int) ($prediction['sizes'][$key] ?? 0);
            $sizes[] = [
                'key' => $key,
                'label' => $label,
                'eggs' => $eggs,
                'trays' => intdiv($eggs, Production::EGGS_PER_TRAY),
                'share' => $predicted > 0 ? round(($eggs / $predicted) * 100, 1) : 0.0,
            ];
        }

        return [
            'predicted_eggs' => $predicted,
            'forecast_date' => $forecastDate->toDateString(),
            'temperature' => $prediction['temperature'],
            'history_years' => (int) config('forecasting.history_years', 3),
            'history_start' => $archive['start'],
            'history_end' => $archive['end'],
            'sample_days' => (int) $prediction['sample_days'],
            'engine' => (string) ($prediction['engine'] ?? 'php'),
            'sizes' => $sizes,
        ];
    }

    /**
     * Saved forecast for a past day, with a fresh size mix fitted to that total.
     * Missing rows fall back to a read-only calculation and are not stored.
     *
     * @return array{
     *     predicted_eggs: int,
     *     forecast_date: string,
     *     temperature: float|null,
     *     history_years: int,
     *     history_start: string,
     *     history_end: string,
     *     sample_days: int,
     *     engine: string,
     *     sizes: list<array{key: string, label: string, eggs: int, trays: int, share: float}>
     * }
     */
    private function savedSummary(Carbon $forecastDate): array
    {
        $summary = $this->forecastSummaryForDate($forecastDate);
        $stored = Forecast::query()
            ->whereDate('forecast_date', $forecastDate->toDateString())
            ->value('predicted_eggs');

        if ($stored === null) {
            return $summary;
        }

        return $this->summaryWithTotal($summary, (int) $stored);
    }

    /**
     * Keep the size mix, but make the eggs add up to the saved forecast total.
     *
     * @param  array{
     *     predicted_eggs: int,
     *     forecast_date: string,
     *     temperature: float|null,
     *     history_years: int,
     *     history_start: string,
     *     history_end: string,
     *     sample_days: int,
     *     engine: string,
     *     sizes: list<array{key: string, label: string, eggs: int, trays: int, share: float}>
     * }  $summary
     * @return array{
     *     predicted_eggs: int,
     *     forecast_date: string,
     *     temperature: float|null,
     *     history_years: int,
     *     history_start: string,
     *     history_end: string,
     *     sample_days: int,
     *     engine: string,
     *     sizes: list<array{key: string, label: string, eggs: int, trays: int, share: float}>
     * }
     */
    private function summaryWithTotal(array $summary, int $total): array
    {
        $summary['predicted_eggs'] = $total;
        $computed = (int) array_sum(array_column($summary['sizes'], 'eggs'));

        if ($total === $computed) {
            return $summary;
        }

        if ($total <= 0 || $computed <= 0) {
            foreach ($summary['sizes'] as $index => $size) {
                $summary['sizes'][$index]['eggs'] = 0;
                $summary['sizes'][$index]['trays'] = 0;
                $summary['sizes'][$index]['share'] = 0.0;
            }

            return $summary;
        }

        $remainders = [];
        $floors = [];
        $allocated = 0;

        foreach ($summary['sizes'] as $index => $size) {
            $exact = $size['eggs'] * $total / $computed;
            $floors[$index] = (int) floor($exact);
            $remainders[$index] = $exact - $floors[$index];
            $allocated += $floors[$index];
        }

        $remaining = $total - $allocated;
        arsort($remainders);

        foreach (array_keys($remainders) as $index) {
            if ($remaining <= 0) {
                break;
            }

            $floors[$index]++;
            $remaining--;
        }

        foreach ($summary['sizes'] as $index => $size) {
            $eggs = $floors[$index];
            $summary['sizes'][$index]['eggs'] = $eggs;
            $summary['sizes'][$index]['trays'] = intdiv($eggs, Production::EGGS_PER_TRAY);
            $summary['sizes'][$index]['share'] = round(($eggs / $total) * 100, 1);
        }

        return $summary;
    }

    /**
     * Mon–Sun series for the dashboard chart (3-year + temperature model).
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

        $cacheKey = 'egg-forecast:v10-rolling-years-tomorrow-temp:'.$weekStart->format('Y-m-d').':'.$weekEnd->format('Y-m-d');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($weekStart, $weekEnd, $empty) {
            try {
                $predictDates = [];
                $cursor = $weekStart->copy()->startOfDay();
                while ($cursor->lte($weekEnd)) {
                    $predictDates[] = $cursor->toDateString();
                    $cursor->addDay();
                }

                $tomorrow = Carbon::tomorrow()->toDateString();
                if (! in_array($tomorrow, $predictDates, true)) {
                    $predictDates[] = $tomorrow;
                }

                $byDate = $this->predictDates($predictDates);
                $labels = [];
                $data = [];
                $series = [];

                foreach ($predictDates as $date) {
                    if ($date === $tomorrow && ! Carbon::parse($date)->betweenIncluded($weekStart, $weekEnd)) {
                        continue;
                    }
                    $prediction = $byDate[$date] ?? null;
                    $eggs = (int) ($prediction['eggs'] ?? 0);
                    $labels[] = Carbon::parse($date)->format('D');
                    $data[] = $eggs;
                    $series[] = [
                        'date' => $date,
                        'label' => Carbon::parse($date)->format('D'),
                        'eggs' => $eggs,
                    ];
                }

                $predicted = (int) ($byDate[$tomorrow]['eggs'] ?? 0);
                $this->persistForecast($tomorrow, $predicted, $series);

                return [
                    'predicted_eggs' => $predicted,
                    'labels' => $labels,
                    'data' => $data,
                ];
            } catch (Throwable $e) {
                Log::warning('Egg forecast generation failed', ['message' => $e->getMessage()]);

                return $empty;
            }
        });
    }

    /**
     * Recompute every saved forecast with the 3-year + temperature rule.
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
     * Forecast total for one date from the 3-year archive (+ that day's temperature when available).
     */
    public function predictedFor(Carbon $forecastDate): int
    {
        return (int) ($this->predictForDate($forecastDate)['eggs'] ?? 0);
    }

    /**
     * @return array{
     *     eggs: int,
     *     temperature: float|null,
     *     sample_days: int,
     *     sizes: array{small: int, medium: int, large: int, extra_large: int, cracked: int},
     *     engine: string
     * }
     */
    private function predictForDate(Carbon $forecastDate): array
    {
        $date = $forecastDate->copy()->startOfDay()->toDateString();
        $predictions = $this->predictDates([$date]);

        return $predictions[$date] ?? [
            'eggs' => 0,
            'temperature' => $this->weather->temperatureForDate($date),
            'sample_days' => 0,
            'sizes' => [
                'small' => 0,
                'medium' => 0,
                'large' => 0,
                'extra_large' => 0,
                'cracked' => 0,
            ],
            'engine' => 'none',
        ];
    }

    /**
     * Prefer the Python service; fall back to the local PHP model if the API is down.
     *
     * @param  list<string>  $predictDates
     * @return array<string, array{
     *     eggs: int,
     *     temperature: float|null,
     *     sample_days: int,
     *     sizes: array{small: int, medium: int, large: int, extra_large: int, cracked: int},
     *     engine: string
     * }>
     */
    private function predictDates(array $predictDates): array
    {
        // Client ask: size mix uses tomorrow's temperature only (not each chart day).
        $sizeTemperature = $this->sizeShiftTemperature(Carbon::tomorrow()->startOfDay());
        $temperatures = [];
        foreach ($predictDates as $date) {
            $temperatures[$date] = $sizeTemperature;
        }

        $archive = $this->archiveBoundsForDates($predictDates);

        try {
            $payload = $this->engine->predict(
                $this->archiveHistoryRows($archive),
                $predictDates,
                7,
                $temperatures,
                [
                    'model' => 'three_year_linear_regression',
                    'history_years' => (int) config('forecasting.history_years', 3),
                    'archive_start' => $archive['start'],
                    'archive_end' => $archive['end'],
                    'neutral_temperature' => (float) config('forecasting.neutral_temperature', 28),
                    'temp_shift_per_degree' => (float) config('forecasting.temp_shift_per_degree', 0.015),
                    'temp_shift_cap' => (float) config('forecasting.temp_shift_cap', 0.20),
                    'eggs_per_tray' => Production::EGGS_PER_TRAY,
                ]
            );

            $model = (string) ($payload['model'] ?? '');
            if ($model !== '' && ! str_contains($model, 'three_year')) {
                throw new \RuntimeException('Forecast API returned legacy model: '.$model);
            }

            $byDate = [];
            foreach ($payload['series'] ?? [] as $point) {
                $date = (string) ($point['date'] ?? '');
                if ($date === '') {
                    continue;
                }
                $sizes = $point['sizes'] ?? ($payload['sizes'] ?? []);
                $mappedSizes = [
                    'small' => (int) ($sizes['small'] ?? 0),
                    'medium' => (int) ($sizes['medium'] ?? 0),
                    'large' => (int) ($sizes['large'] ?? 0),
                    'extra_large' => (int) ($sizes['extra_large'] ?? 0),
                    'cracked' => (int) ($sizes['cracked'] ?? 0),
                ];
                $eggs = (int) ($point['eggs'] ?? 0);
                if ($eggs > 0 && array_sum($mappedSizes) <= 0) {
                    throw new \RuntimeException('Forecast API returned totals without size breakdown.');
                }
                $byDate[$date] = [
                    'eggs' => $eggs,
                    'temperature' => isset($point['temperature'])
                        ? (float) $point['temperature']
                        : ($temperatures[$date] ?? null),
                    'sample_days' => (int) ($point['sample_days'] ?? $payload['sample_days'] ?? 0),
                    'sizes' => $mappedSizes,
                    'engine' => 'python',
                ];
            }

            if ($byDate !== []) {
                return $byDate;
            }
        } catch (Throwable $e) {
            Log::warning('Python egg forecast unavailable, using local fallback', [
                'message' => $e->getMessage(),
            ]);
        }

        $fallback = [];
        foreach ($predictDates as $date) {
            $fallback[$date] = $this->localPredictForDate(Carbon::parse($date)->startOfDay());
        }

        return $fallback;
    }

    /**
     * @param  array{start: string, end: string}|null  $archive
     * @return list<array{date: string, eggs: int, small: int, medium: int, large: int, extra_large: int, cracked: int}>
     */
    private function archiveHistoryRows(?array $archive = null): array
    {
        $archive ??= $this->archiveBounds();

        $rows = Production::query()
            ->whereBetween('production_date', [$archive['start'], $archive['end']])
            ->selectRaw('
                DATE(production_date) as date,
                COALESCE(SUM(eggs_produced), 0) as eggs,
                COALESCE(SUM(small_eggs), 0) as small_eggs,
                COALESCE(SUM(medium_eggs), 0) as medium_eggs,
                COALESCE(SUM(large_eggs), 0) as large_eggs,
                COALESCE(SUM(extra_large_eggs), 0) as extra_large_eggs,
                COALESCE(SUM(cracked_eggs), 0) as cracked_eggs
            ')
            ->groupBy(DB::raw('DATE(production_date)'))
            ->orderBy(DB::raw('DATE(production_date)'))
            ->get();

        $history = [];
        foreach ($rows as $row) {
            $eggs = (int) $row->eggs;
            if ($eggs <= 0) {
                continue;
            }
            $history[] = [
                'date' => Carbon::parse($row->date)->toDateString(),
                'eggs' => $eggs,
                'small' => (int) $row->small_eggs,
                'medium' => (int) $row->medium_eggs,
                'large' => (int) $row->large_eggs,
                'extra_large' => (int) $row->extra_large_eggs,
                'cracked' => (int) $row->cracked_eggs,
            ];
        }

        return $history;
    }

    /**
     * Local PHP copy: linear regression on 3-year archive + temperature size mix.
     *
     * @return array{
     *     eggs: int,
     *     temperature: float|null,
     *     sample_days: int,
     *     sizes: array{small: int, medium: int, large: int, extra_large: int, cracked: int},
     *     engine: string
     * }
     */
    private function localPredictForDate(Carbon $forecastDate): array
    {
        $forecastDate = $forecastDate->copy()->startOfDay();
        $temperature = $this->sizeShiftTemperature($forecastDate);
        $dated = $this->sameCalendarDayDatedSamples($forecastDate);

        if ($dated === []) {
            $samples = $this->sameCalendarDaySamples($forecastDate);
            if ($samples === []) {
                return [
                    'eggs' => 0,
                    'temperature' => $temperature,
                    'sample_days' => 0,
                    'sizes' => [
                        'small' => 0,
                        'medium' => 0,
                        'large' => 0,
                        'extra_large' => 0,
                        'cracked' => 0,
                    ],
                    'engine' => 'php',
                ];
            }
            $years = [];
            $ys = [];
            $count = count($samples);
            foreach (array_reverse($samples) as $index => $sample) {
                $years[] = (float) ($forecastDate->year - $count + $index);
                $ys[] = (float) $sample['eggs'];
            }
        } else {
            $years = array_map(fn ($row) => (float) $row['year'], $dated);
            $ys = array_map(fn ($row) => (float) $row['eggs'], $dated);
            $samples = array_map(fn ($row) => $row['mix'], $dated);
        }

        if (count($ys) === 1) {
            $predicted = (int) (round($ys[0] / Production::EGGS_PER_TRAY) * Production::EGGS_PER_TRAY);
        } else {
            [$slope, $intercept] = $this->fitLine($years, $ys);
            $predicted = (int) max(0, round($slope * $forecastDate->year + $intercept));
            $predicted = (int) (round($predicted / Production::EGGS_PER_TRAY) * Production::EGGS_PER_TRAY);
            if ($predicted <= 0) {
                $avgEggs = array_sum($ys) / count($ys);
                $predicted = (int) (round($avgEggs / Production::EGGS_PER_TRAY) * Production::EGGS_PER_TRAY);
            }
        }

        $baseMix = [
            'small' => 0,
            'medium' => 0,
            'large' => 0,
            'extra_large' => 0,
            'cracked' => 0,
        ];
        foreach ($samples as $sample) {
            foreach ($baseMix as $key => $_) {
                $baseMix[$key] += (int) ($sample[$key] ?? 0);
            }
        }

        $shifted = $this->shiftMixForTemperature($baseMix, $temperature);
        $sizes = $this->allocateByMix($predicted, $shifted);

        return [
            'eggs' => $predicted,
            'temperature' => $temperature,
            'sample_days' => count($samples),
            'sizes' => $sizes,
            'engine' => 'php',
        ];
    }

    /**
     * @return list<array{year: int, eggs: int, mix: array{small: int, medium: int, large: int, extra_large: int, cracked: int}}>
     */
    private function sameCalendarDayDatedSamples(Carbon $forecastDate): array
    {
        $archive = $this->archiveBounds($forecastDate);
        $startYear = Carbon::parse($archive['start'])->year;
        $endYear = Carbon::parse($archive['end'])->year;
        $limit = max(1, (int) config('forecasting.history_years', 3));
        $rows = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            if (! checkdate($forecastDate->month, $forecastDate->day, $year)) {
                continue;
            }
            $date = Carbon::create($year, $forecastDate->month, $forecastDate->day)->startOfDay();
            if ($date->gte($forecastDate) || $date->gt(Carbon::parse($archive['end']))) {
                continue;
            }

            $row = Production::query()
                ->whereDate('production_date', $date->toDateString())
                ->selectRaw('
                    COALESCE(SUM(eggs_produced), 0) as eggs,
                    COALESCE(SUM(small_eggs), 0) as small_eggs,
                    COALESCE(SUM(medium_eggs), 0) as medium_eggs,
                    COALESCE(SUM(large_eggs), 0) as large_eggs,
                    COALESCE(SUM(extra_large_eggs), 0) as extra_large_eggs,
                    COALESCE(SUM(cracked_eggs), 0) as cracked_eggs
                ')
                ->first();

            $eggs = (int) ($row->eggs ?? 0);
            if ($eggs <= 0) {
                continue;
            }

            $rows[] = [
                'year' => $year,
                'eggs' => $eggs,
                'mix' => [
                    'small' => (int) ($row->small_eggs ?? 0),
                    'medium' => (int) ($row->medium_eggs ?? 0),
                    'large' => (int) ($row->large_eggs ?? 0),
                    'extra_large' => (int) ($row->extra_large_eggs ?? 0),
                    'cracked' => (int) ($row->cracked_eggs ?? 0),
                ],
            ];
        }

        if ($rows === []) {
            return [];
        }

        return array_slice($rows, -$limit);
    }

    /**
     * @param  list<float|int>  $x
     * @param  list<float|int>  $y
     * @return array{0: float, 1: float}
     */
    private function fitLine(array $x, array $y): array
    {
        $n = count($x);
        if ($n === 0) {
            return [0.0, 0.0];
        }
        if ($n === 1) {
            return [0.0, (float) $y[0]];
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXy = 0.0;
        $sumXx = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sumXy += $x[$i] * $y[$i];
            $sumXx += $x[$i] * $x[$i];
        }
        $denominator = $n * $sumXx - $sumX * $sumX;
        if ($denominator == 0.0) {
            return [0.0, $sumY / $n];
        }

        $slope = ($n * $sumXy - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [$slope, $intercept];
    }

    /**
     * Same month/day across the archive years (skips the forecast date itself).
     *
     * @return list<array{eggs: int, small: int, medium: int, large: int, extra_large: int, cracked: int}>
     */
    private function sameCalendarDaySamples(Carbon $forecastDate): array
    {
        $archive = $this->archiveBounds($forecastDate);
        $startYear = Carbon::parse($archive['start'])->year;
        $endYear = Carbon::parse($archive['end'])->year;
        $limit = max(1, (int) config('forecasting.history_years', 3));

        $byYear = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            if (! checkdate($forecastDate->month, $forecastDate->day, $year)) {
                continue;
            }

            $date = Carbon::create($year, $forecastDate->month, $forecastDate->day)->startOfDay();
            if ($date->gte($forecastDate) || $date->gt(Carbon::parse($archive['end']))) {
                continue;
            }

            $row = Production::query()
                ->whereDate('production_date', $date->toDateString())
                ->selectRaw('
                    COALESCE(SUM(eggs_produced), 0) as eggs,
                    COALESCE(SUM(small_eggs), 0) as small_eggs,
                    COALESCE(SUM(medium_eggs), 0) as medium_eggs,
                    COALESCE(SUM(large_eggs), 0) as large_eggs,
                    COALESCE(SUM(extra_large_eggs), 0) as extra_large_eggs,
                    COALESCE(SUM(cracked_eggs), 0) as cracked_eggs
                ')
                ->first();

            $eggs = (int) ($row->eggs ?? 0);
            if ($eggs <= 0) {
                continue;
            }

            $byYear[$year] = [
                'eggs' => $eggs,
                'small' => (int) ($row->small_eggs ?? 0),
                'medium' => (int) ($row->medium_eggs ?? 0),
                'large' => (int) ($row->large_eggs ?? 0),
                'extra_large' => (int) ($row->extra_large_eggs ?? 0),
                'cracked' => (int) ($row->cracked_eggs ?? 0),
            ];
        }

        if ($byYear !== []) {
            krsort($byYear);

            return array_values(array_slice($byYear, 0, $limit, true));
        }

        return $this->nearbyDayOfYearSamples($forecastDate, $startYear, $endYear, $archive['end'], $limit);
    }

    /**
     * Fallback: average nearby day-of-year rows when the exact calendar day is missing.
     *
     * @return list<array{eggs: int, small: int, medium: int, large: int, extra_large: int, cracked: int}>
     */
    private function nearbyDayOfYearSamples(
        Carbon $forecastDate,
        int $startYear,
        int $endYear,
        string $archiveEnd,
        int $limit
    ): array {
        $targetDoy = $forecastDate->dayOfYear;
        $byYear = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearEggs = 0;
            $yearMix = [
                'small' => 0,
                'medium' => 0,
                'large' => 0,
                'extra_large' => 0,
                'cracked' => 0,
            ];
            $hits = 0;

            for ($offset = -3; $offset <= 3; $offset++) {
                $date = Carbon::create($year, 1, 1)->startOfDay()->addDays($targetDoy - 1 + $offset);
                if ($date->year !== $year || $date->gte($forecastDate) || $date->gt(Carbon::parse($archiveEnd))) {
                    continue;
                }

                $row = Production::query()
                    ->whereDate('production_date', $date->toDateString())
                    ->selectRaw('
                        COALESCE(SUM(eggs_produced), 0) as eggs,
                        COALESCE(SUM(small_eggs), 0) as small_eggs,
                        COALESCE(SUM(medium_eggs), 0) as medium_eggs,
                        COALESCE(SUM(large_eggs), 0) as large_eggs,
                        COALESCE(SUM(extra_large_eggs), 0) as extra_large_eggs,
                        COALESCE(SUM(cracked_eggs), 0) as cracked_eggs
                    ')
                    ->first();

                $eggs = (int) ($row->eggs ?? 0);
                if ($eggs <= 0) {
                    continue;
                }

                $yearEggs += $eggs;
                $yearMix['small'] += (int) ($row->small_eggs ?? 0);
                $yearMix['medium'] += (int) ($row->medium_eggs ?? 0);
                $yearMix['large'] += (int) ($row->large_eggs ?? 0);
                $yearMix['extra_large'] += (int) ($row->extra_large_eggs ?? 0);
                $yearMix['cracked'] += (int) ($row->cracked_eggs ?? 0);
                $hits++;
            }

            if ($hits === 0) {
                continue;
            }

            $byYear[$year] = [
                'eggs' => (int) round($yearEggs / $hits),
                'small' => (int) round($yearMix['small'] / $hits),
                'medium' => (int) round($yearMix['medium'] / $hits),
                'large' => (int) round($yearMix['large'] / $hits),
                'extra_large' => (int) round($yearMix['extra_large'] / $hits),
                'cracked' => (int) round($yearMix['cracked'] / $hits),
            ];
        }

        krsort($byYear);

        return array_values(array_slice($byYear, 0, $limit, true));
    }

    /**
     * Hotter than neutral → more Small, fewer Large/XL.
     * Cooler than neutral → more Large, fewer Small.
     *
     * @param  array{small: int, medium: int, large: int, extra_large: int, cracked: int}  $mix
     * @return array{small: int, medium: int, large: int, extra_large: int, cracked: int}
     */
    private function shiftMixForTemperature(array $mix, ?float $temperature): array
    {
        if ($temperature === null) {
            return $mix;
        }

        $neutral = (float) config('forecasting.neutral_temperature', 28);
        $perDegree = (float) config('forecasting.temp_shift_per_degree', 0.015);
        $cap = (float) config('forecasting.temp_shift_cap', 0.20);
        $shift = max(-$cap, min($cap, ($temperature - $neutral) * $perDegree));

        if (abs($shift) < 0.0001) {
            return $mix;
        }

        $transferable = $mix['small'] + $mix['large'] + $mix['extra_large'];
        if ($transferable <= 0) {
            return $mix;
        }

        $move = (int) round($transferable * abs($shift));
        if ($move <= 0) {
            return $mix;
        }

        $result = $mix;

        if ($shift > 0) {
            // Hotter: Large/XL → Small
            $fromLarge = min($result['large'], (int) round($move * 0.7));
            $fromXl = min($result['extra_large'], $move - $fromLarge);
            $result['large'] -= $fromLarge;
            $result['extra_large'] -= $fromXl;
            $result['small'] += $fromLarge + $fromXl;
        } else {
            // Cooler: Small → Large
            $fromSmall = min($result['small'], $move);
            $result['small'] -= $fromSmall;
            $result['large'] += $fromSmall;
        }

        return $result;
    }

    /**
     * @param  array{small: int, medium: int, large: int, extra_large: int, cracked: int}  $mix
     * @return array{small: int, medium: int, large: int, extra_large: int, cracked: int}
     */
    private function allocateByMix(int $total, array $mix): array
    {
        $keys = ['small', 'medium', 'large', 'extra_large', 'cracked'];
        $empty = array_fill_keys($keys, 0);

        if ($total <= 0) {
            return $empty;
        }

        $weightSum = array_sum($mix);
        if ($weightSum <= 0) {
            $empty['medium'] = $total;

            return $empty;
        }

        $parts = [];
        $assigned = 0;
        $remainders = [];

        foreach ($keys as $key) {
            $exact = $total * ($mix[$key] / $weightSum);
            $base = (int) floor($exact);
            $parts[$key] = $base;
            $assigned += $base;
            $remainders[$key] = $exact - $base;
        }

        $left = $total - $assigned;
        arsort($remainders);
        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            $parts[$key]++;
            $left--;
        }

        return $parts;
    }

    /**
     * Rolling last N completed calendar years before the forecast year.
     * 2026 forecast → 2023–2025; 2027 forecast → 2024–2026.
     *
     * @return array{start: string, end: string}
     */
    private function archiveBounds(?Carbon $forecastDate = null): array
    {
        $forecastDate = ($forecastDate ?? Carbon::tomorrow())->copy()->startOfDay();
        $years = max(1, (int) config('forecasting.history_years', 3));

        $endYear = $forecastDate->year - 1;
        $startYear = $endYear - $years + 1;

        $floor = Carbon::parse((string) config(
            'forecasting.archive_start',
            Production::sampleStartDate()
        ))->year;
        if ($startYear < $floor) {
            $startYear = $floor;
        }

        $configuredEnd = trim((string) config('forecasting.archive_end', ''));
        if ($configuredEnd !== '') {
            $capYear = Carbon::parse($configuredEnd)->year;
            if ($endYear > $capYear) {
                $endYear = $capYear;
                $startYear = max($floor, $endYear - $years + 1);
            }
        }

        if ($startYear > $endYear) {
            $startYear = $endYear;
        }

        return [
            'start' => sprintf('%04d-01-01', $startYear),
            'end' => sprintf('%04d-12-31', $endYear),
        ];
    }

    /**
     * @param  list<string>  $predictDates
     * @return array{start: string, end: string}
     */
    private function archiveBoundsForDates(array $predictDates): array
    {
        if ($predictDates === []) {
            return $this->archiveBounds();
        }

        $starts = [];
        $ends = [];
        foreach ($predictDates as $date) {
            $bounds = $this->archiveBounds(Carbon::parse($date)->startOfDay());
            $starts[] = $bounds['start'];
            $ends[] = $bounds['end'];
        }

        return [
            'start' => min($starts),
            'end' => max($ends),
        ];
    }

    /**
     * Live forecasts always shift sizes with tomorrow's temperature.
     * Past recomputes keep that historical day's temperature when available.
     */
    private function sizeShiftTemperature(Carbon $forecastDate): ?float
    {
        $today = Carbon::today()->startOfDay();
        if ($forecastDate->greaterThanOrEqualTo($today)) {
            return $this->weather->temperatureForDate(Carbon::tomorrow()->startOfDay());
        }

        return $this->weather->temperatureForDate($forecastDate);
    }

    /**
     * @param  list<array{date: string, label: string, eggs: int}>  $series
     */
    private function persistForecast(string $forecastDate, int $predicted, array $series): void
    {
        $attributes = ['predicted_eggs' => $predicted];
        if (Schema::hasColumn('forecasts', 'series')) {
            $attributes['series'] = $series;
        }

        Forecast::updateOrCreate(
            ['forecast_date' => $forecastDate],
            $attributes
        );
    }
}
