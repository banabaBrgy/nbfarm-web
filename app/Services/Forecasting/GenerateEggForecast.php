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
     * Total: ARIMA(2,1,1) on daily harvest history.
     * Sizes: historical mix, shifted by tomorrow's temperature.
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
     * Forecast saved for the production date on screen.
     * October 24 loads the forecast saved for October 24, not the next day.
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
        return $this->savedSummary($productionDate->copy()->startOfDay());
    }

    /**
     * Forecast for the day before the selected production date.
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
        return $this->savedSummary($productionDate->copy()->startOfDay()->subDay());
    }

    /**
     * Next-day prediction relative to a dashboard reference date.
     * Today keeps the saved tomorrow forecast. Past dates stay read-only.
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
    public function summaryFrom(?Carbon $asOf = null): array
    {
        $asOf = $asOf?->copy()->startOfDay() ?? Carbon::today();

        if ($asOf->greaterThanOrEqualTo(Carbon::today())) {
            return $this->summary();
        }

        $forecastDate = $asOf->copy()->addDay();

        if ($forecastDate->equalTo(Carbon::today())) {
            return $this->previousSummary();
        }

        return $this->savedSummary($forecastDate);
    }

    /**
     * Forecast for the selected date itself (the day before the next-day card).
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
    public function previousSummaryFrom(?Carbon $asOf = null): array
    {
        if ($asOf === null || $asOf->copy()->startOfDay()->greaterThanOrEqualTo(Carbon::today())) {
            return $this->previousSummary();
        }

        return $this->savedSummary($asOf->copy()->startOfDay());
    }

    /**
     * Egg totals for the next N days starting tomorrow, or from a given date.
     * Read-only: does not write forecast rows. The saved row for a date
     * stays the expected number the production-drop alert compares later.
     *
     * @return list<array{date: string, eggs: int}>
     */
    public function upcomingDays(int $days = 7, ?Carbon $startFrom = null): array
    {
        $days = max(1, $days);
        $dates = [];
        $cursor = ($startFrom ?? Carbon::tomorrow())->copy()->startOfDay();

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
     * Read-only egg totals for an arbitrary set of dates.
     * Does not write forecast rows. Zero means the model had nothing to use.
     *
     * @param  list<string>  $dates
     * @return array<string, int>
     */
    public function totalsForDates(array $dates): array
    {
        $totals = [];
        foreach ($this->breakdownForDates($dates) as $date => $row) {
            $totals[$date] = $row['eggs'];
        }

        return $totals;
    }

    /**
     * Read-only forecast total and size mix for chart dates.
     * Does not write forecast rows. Dates the model cannot predict are omitted.
     *
     * @param  list<string>  $dates
     * @return array<string, array{eggs: int, sizes: array{small: int, medium: int, large: int, extra_large: int, cracked: int}}>
     */
    public function breakdownForDates(array $dates): array
    {
        $normalized = [];
        foreach ($dates as $date) {
            if (! is_string($date) || $date === '') {
                continue;
            }
            try {
                $normalized[] = Carbon::parse($date)->toDateString();
            } catch (Throwable) {
                continue;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);
        if ($normalized === []) {
            return [];
        }

        $stamp = Production::query()
            ->selectRaw('COUNT(*) as row_count, COALESCE(SUM(eggs_produced), 0) as eggs, MAX(production_date) as latest')
            ->first();
        $cacheKey = 'egg-forecast:chart-breakdown:v7day:'.md5(implode('|', [
            (string) ($stamp->row_count ?? 0),
            (string) ($stamp->eggs ?? 0),
            (string) ($stamp->latest ?? 'none'),
            implode(',', $normalized),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($normalized) {
            $rows = [];
            foreach (array_chunk($normalized, 28) as $chunk) {
                try {
                    $predicted = $this->predictDates($chunk);
                } catch (Throwable $e) {
                    Log::warning('Chart date forecast chunk failed', [
                        'message' => $e->getMessage(),
                    ]);
                    $predicted = [];
                }

                foreach ($chunk as $date) {
                    $point = $predicted[$date] ?? null;
                    $eggs = (int) ($point['eggs'] ?? 0);
                    if ($eggs <= 0 || ! is_array($point)) {
                        continue;
                    }

                    $sizes = is_array($point['sizes'] ?? null) ? $point['sizes'] : [];
                    $rows[$date] = [
                        'eggs' => $eggs,
                        'sizes' => [
                            'small' => (int) ($sizes['small'] ?? 0),
                            'medium' => (int) ($sizes['medium'] ?? 0),
                            'large' => (int) ($sizes['large'] ?? 0),
                            'extra_large' => (int) ($sizes['extra_large'] ?? 0),
                            'cracked' => (int) ($sizes['cracked'] ?? 0),
                        ],
                    ];
                }
            }

            return $rows;
        });
    }

    /**
     * Monthly forecast totals for a calendar year (dashboard year chart).
     * Days with harvest records are predicted in 7-day-ahead ARIMA blocks
     * and summed so the series lines up with Actual monthly harvest dates.
     *
     * 2023 (archive start year) has no earlier history for ARIMA, so those days
     * use a within-year prior-7-day average.
     *
     * @return list<int|null>
     */
    public function monthlyTotalsForYear(int $year): array
    {
        $empty = array_fill(0, 12, null);

        $dateExpression = DB::connection()->getDriverName() === 'sqlite'
            ? 'date(production_date)'
            : 'DATE(production_date)';

        $dates = Production::query()
            ->whereYear('production_date', $year)
            ->selectRaw("{$dateExpression} as date")
            ->groupBy(DB::raw($dateExpression))
            ->orderBy(DB::raw($dateExpression))
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();

        if ($dates === []) {
            return $empty;
        }

        $maxDate = max($dates);
        $cacheKey = 'egg-forecast:monthly-year:v3:'.$year.':'.$maxDate;

        return Cache::remember($cacheKey, now()->addHour(), function () use ($dates, $empty, $year) {
            $archiveStartYear = Carbon::parse((string) config(
                'forecasting.archive_start',
                '2023-01-01'
            ))->year;

            $byDate = [];

            if ($year > $archiveStartYear) {
                foreach (array_chunk($dates, 28) as $chunk) {
                    try {
                        $byDate += $this->predictDates($chunk);
                    } catch (Throwable $e) {
                        Log::warning('Yearly egg forecast chunk failed', [
                            'year' => $year,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $predictedSum = (int) array_sum(array_map(
                fn ($date) => (int) ($byDate[$date]['eggs'] ?? 0),
                $dates
            ));

            if ($year <= $archiveStartYear || $predictedSum <= 0) {
                $byDate = $this->withinYearBaselineForDates($dates);
            }

            $totals = array_fill(0, 12, 0);
            $hasMonth = array_fill(0, 12, false);

            foreach ($dates as $date) {
                $monthIndex = Carbon::parse($date)->month - 1;
                $hasMonth[$monthIndex] = true;
                $totals[$monthIndex] += (int) ($byDate[$date]['eggs'] ?? 0);
            }

            $result = [];
            for ($month = 0; $month < 12; $month++) {
                $result[] = $hasMonth[$month] ? $totals[$month] : null;
            }

            return $result !== [] ? $result : $empty;
        });
    }

    /**
     * First-archive-year chart fallback: average of up to 7 prior harvest days
     * in the same year, rounded to trays.
     *
     * @param  list<string>  $dates
     * @return array<string, array{eggs: int}>
     */
    private function withinYearBaselineForDates(array $dates): array
    {
        $ordered = array_values($dates);
        sort($ordered);

        $year = Carbon::parse($ordered[0])->year;
        $wanted = array_fill_keys($ordered, true);
        $actualByDate = [];

        $rows = Production::query()
            ->whereYear('production_date', $year)
            ->orderBy('production_date')
            ->get(['production_date', 'eggs_produced']);

        foreach ($rows as $row) {
            $key = Carbon::parse($row->production_date)->toDateString();
            if (! isset($wanted[$key])) {
                continue;
            }
            $actualByDate[$key] = ($actualByDate[$key] ?? 0) + (int) $row->eggs_produced;
        }

        $tray = Production::EGGS_PER_TRAY;
        $result = [];

        foreach ($ordered as $index => $date) {
            $window = [];
            for ($j = $index - 1; $j >= 0 && count($window) < 7; $j--) {
                $prior = $ordered[$j];
                $eggs = (int) ($actualByDate[$prior] ?? 0);
                if ($eggs > 0) {
                    $window[] = $eggs;
                }
            }

            if ($window === []) {
                $result[$date] = ['eggs' => 0];

                continue;
            }

            $avg = array_sum($window) / count($window);
            $result[$date] = [
                'eggs' => (int) (round($avg / $tray) * $tray),
            ];
        }

        return $result;
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
     * Forecast totals come from the Python ARIMA(2,1,1) service.
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
        $requestedModel = $this->forecastModel();

        try {
            $payload = $this->engine->predict(
                $this->archiveHistoryRows($archive),
                $predictDates,
                7,
                $temperatures,
                [
                    'model' => $requestedModel,
                    'history_years' => (int) config('forecasting.history_years', 3),
                    'archive_start' => $archive['start'],
                    'archive_end' => $archive['end'],
                    'neutral_temperature' => (float) config('forecasting.neutral_temperature', 28),
                    'temp_shift_per_degree' => (float) config('forecasting.temp_shift_per_degree', 0.015),
                    'temp_shift_cap' => (float) config('forecasting.temp_shift_cap', 0.20),
                    'eggs_per_tray' => Production::EGGS_PER_TRAY,
                ]
            );

            if (! empty($payload['error'])) {
                throw new \RuntimeException((string) $payload['error']);
            }

            $model = (string) ($payload['model'] ?? '');
            if ($model !== '' && ! $this->isAcceptedForecastModel($model)) {
                throw new \RuntimeException('Forecast API returned unsupported model: '.$model);
            }

            $warnings = $payload['warnings'] ?? [];
            if (is_array($warnings) && $warnings !== []) {
                Log::warning('ARIMA skipped some forecast dates', ['warnings' => $warnings]);
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

            throw new \RuntimeException('ARIMA returned no forecast values.');
        } catch (Throwable $e) {
            Log::warning('Python ARIMA forecast unavailable', [
                'message' => $e->getMessage(),
                'model' => 'arima_2_1_1',
            ]);

            return [];
        }
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
        return $this->arimaArchiveBounds($predictDates);
    }

    /**
     * Consecutive daily harvests through today. Python then trains each
     * 7-day block only on days before that block, so a past chart week
     * cannot see its own actuals.
     *
     * @param  list<string>  $predictDates
     * @return array{start: string, end: string}
     */
    private function arimaArchiveBounds(array $predictDates): array
    {
        unset($predictDates);

        $floor = Carbon::parse((string) config(
            'forecasting.archive_start',
            Production::sampleStartDate()
        ))->startOfDay();

        $end = Carbon::today()->startOfDay();
        $configuredEnd = trim((string) config('forecasting.archive_end', ''));
        if ($configuredEnd !== '') {
            $cap = Carbon::parse($configuredEnd)->startOfDay();
            if ($end->gt($cap)) {
                $end = $cap;
            }
        }
        if ($end->lt($floor)) {
            $end = $floor->copy();
        }

        return [
            'start' => $floor->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    private function forecastModel(): string
    {
        return 'arima_2_1_1';
    }

    private function isAcceptedForecastModel(string $model): bool
    {
        return str_contains(strtolower($model), 'arima');
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
