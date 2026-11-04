<?php

namespace App\Services;

use App\Models\Production;
use App\Services\Forecasting\GenerateEggForecast;
use Illuminate\Support\Facades\DB;

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
