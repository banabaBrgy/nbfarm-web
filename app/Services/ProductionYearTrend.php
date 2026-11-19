<?php

namespace App\Services;

use App\Models\Production;
use App\Services\Forecasting\GenerateEggForecast;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionYearTrend
{
    /** @var list<int> */
    private const YEARS = [2023, 2024, 2025, 2026];

    /** @var list<string> */
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** @var list<string> */
    private const COLORS = ['#2e7d32', '#e07a3f', '#1565c0', '#6a1b9a', '#00838f'];

    private const ACTUAL_COLOR = '#2d7d46';

    private const FORECAST_COLOR = '#f0a030';

    public function __construct(
        private GenerateEggForecast $forecast,
    ) {}

    /**
     * Daily actual egg production and, when requested, historical forecasts within a selected date range.
     */
    public function forDateRange(?string $startDate = null, ?string $endDate = null, bool $includeForecast = true): array
    {
        if (! $startDate || ! $endDate) {
            return [
                'labels' => [],
                'series' => [],
                'startDate' => null,
                'endDate' => null,
            ];
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($start->gt($end)) {
            return [
                'labels' => [],
                'series' => [],
                'startDate' => null,
                'endDate' => null,
            ];
        }

        $startDay = $start->toDateString();
        $endDay = $end->toDateString();

        $rows = Production::query()
            ->selectRaw('DATE(production_date) as production_day, SUM(eggs_produced) as total')
            ->whereBetween('production_date', [$startDay, $endDay])
            ->groupBy(DB::raw('DATE(production_date)'))
            ->get()
            ->keyBy('production_day');

        $forecastByDate = [];

        if ($includeForecast) {
            $days = [];
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
                $days[] = $cursor->toDateString();
            }

            foreach ($this->forecast->totalsForDates($days) as $day => $live) {
                $eggs = (int) $live;
                if ($eggs > 0) {
                    $forecastByDate[$day] = $eggs;
                }
            }
        }

        $labels = [];
        $actualData = [];
        $forecastData = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $date->toDateString();

            $labels[] = $date->format('M d, Y');

            $row = $rows->get($day);

            $actualData[] = $row ? (int) $row->total : null;
            $forecastData[] = $forecastByDate[$day] ?? null;
        }

        if ($includeForecast) {
            Log::info('HISTORICAL BACKTEST CHART DATA', [
                'start_date' => $startDay,
                'end_date' => $endDay,
                'actual_count' => count(array_filter($actualData, fn ($value) => $value !== null)),
                'forecast_count' => count(array_filter($forecastData, fn ($value) => $value !== null)),
            ]);
        }

        $series = [
            [
                'label' => 'Actual Production',
                'data' => $actualData,
                'color' => self::COLORS[0],
            ],
        ];

        if ($includeForecast) {
            $series[] = [
                'label' => 'Forecast',
                'data' => $forecastData,
                'color' => self::COLORS[1],
            ];
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'startDate' => $startDay,
            'endDate' => $endDay,
        ];
    }

    /**
     * Monthly egg totals by year for the historical trend line graph.
     *
     * @return array{
     *     months: list<string>,
     *     years: list<int>,
     *     selectedYear: int|null,
     *     series: list<array{label: string, data: list<int|null>, color: string, style?: string}>
     * }
     */
    public function forYear(string|int|null $yearFilter = null): array
    {
        $years = self::YEARS;

        $selectedYear = is_numeric($yearFilter) ? (int) $yearFilter : null;
        if ($selectedYear !== null && ! in_array($selectedYear, $years, true)) {
            $selectedYear = null;
        }

        $driver = DB::connection()->getDriverName();
        $yearExpression = $driver === 'sqlite'
            ? "strftime('%Y', production_date)"
            : 'YEAR(production_date)';
        $monthExpression = $driver === 'sqlite'
            ? "CAST(strftime('%m', production_date) AS INTEGER)"
            : 'MONTH(production_date)';

        $query = Production::query()
            ->selectRaw("
                {$yearExpression} as year,
                {$monthExpression} as month,
                SUM(eggs_produced) as total
            ")
            ->groupBy(DB::raw($yearExpression), DB::raw($monthExpression));

        if ($selectedYear !== null) {
            $query->whereYear('production_date', $selectedYear);
        }

        $rows = $query->get();

        if ($selectedYear !== null) {
            $actual = [];
            for ($month = 1; $month <= 12; $month++) {
                $row = $rows->first(
                    fn ($item) => (int) $item->year === $selectedYear && (int) $item->month === $month
                );
                $total = $row ? (int) $row->total : 0;
                $actual[] = $total > 0 ? $total : null;
            }

            return [
                'months' => self::MONTHS,
                'years' => $years,
                'selectedYear' => $selectedYear,
                'series' => [
                    [
                        'label' => 'Actual',
                        'data' => $actual,
                        'color' => self::ACTUAL_COLOR,
                    ],
                    [
                        'label' => 'Forecast',
                        'data' => $this->forecast->monthlyTotalsForYear($selectedYear),
                        'color' => self::FORECAST_COLOR,
                        'style' => 'forecast',
                    ],
                ],
            ];
        }

        $series = [];

        foreach ($years as $index => $year) {
            $data = [];
            for ($month = 1; $month <= 12; $month++) {
                $row = $rows->first(
                    fn ($item) => (int) $item->year === $year && (int) $item->month === $month
                );
                $total = $row ? (int) $row->total : 0;
                $data[] = $total > 0 ? $total : null;
            }

            $series[] = [
                'label' => (string) $year,
                'data' => $data,
                'color' => self::COLORS[$index % count(self::COLORS)],
            ];
        }

        return [
            'months' => self::MONTHS,
            'years' => $years,
            'selectedYear' => $selectedYear,
            'series' => $series,
        ];
    }
}
