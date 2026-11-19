<?php

namespace App\Services;

use App\Services\Forecasting\GenerateEggForecast;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EggProductionChart
{
    public const VIEWS = ['historical', 'comparison', 'timeseries', 'forecast'];

    /** Forward predicted trend. Longer than the seven-day view so the two steps are not the same chart. */
    public const TIME_SERIES_DAYS = 30;

    public const SEVEN_DAYS = 7;

    /** Inclusive day count allowed for past-production views. */
    public const MAX_RANGE_DAYS = 366;

    public function __construct(
        private ProductionYearTrend $trends,
        private GenerateEggForecast $forecasts,
    ) {}

    /**
     * @return array{
     *     view: string,
     *     period: string,
     *     needsDates: bool,
     *     rangeLocked: bool,
     *     startDate: ?string,
     *     endDate: ?string,
     *     emptyMessage: ?string,
     *     notice: ?string,
     *     labels: list<string>,
     *     datasets: list<array{label: string, data: list<int|float|null>, forecast: bool}>
     * }
     */
    public function forRequest(Request $request, ?DashboardDateFilter $filter = null): array
    {
        $filter ??= DashboardDateFilter::fromRequest($request);
        $lockDates = ! $filter->isToday();
        $view = $this->view($request);

        if ($lockDates && $filter->isRange() && ! in_array($view, ['historical', 'comparison'], true)) {
            $view = 'historical';
        }

        $startDate = $lockDates ? $filter->startDate() : $request->query('start_date');
        $endDate = $lockDates ? $filter->endDate() : $request->query('end_date');

        $payload = match ($view) {
            'historical' => $this->past($view, $startDate, $endDate, true),
            'comparison' => $this->past($view, $startDate, $endDate, true),
            'timeseries' => $this->forward('timeseries', self::TIME_SERIES_DAYS, $filter->forecastStart()),
            default => $this->forward('forecast', self::SEVEN_DAYS, $filter->forecastStart()),
        };

        $payload['rangeLocked'] = $filter->isRange();
        if ($lockDates) {
            $payload['needsDates'] = false;
        }

        return $payload;
    }

    private function view(Request $request): string
    {
        $requested = $request->query('trend_view', '');
        $view = is_string($requested) ? $requested : '';

        if ($view === 'custom') {
            return 'comparison';
        }

        if (in_array($view, self::VIEWS, true)) {
            return $view;
        }

        if ($view === '' && $request->filled('start_date') && $request->filled('end_date')) {
            return 'comparison';
        }

        return 'forecast';
    }

    /**
     * @return array{
     *     view: string,
     *     period: string,
     *     needsDates: bool,
     *     rangeLocked: bool,
     *     startDate: ?string,
     *     endDate: ?string,
     *     emptyMessage: ?string,
     *     notice: ?string,
     *     labels: list<string>,
     *     datasets: list<array{label: string, data: list<int|float|null>, forecast: bool}>
     * }
     */
    private function past(string $view, mixed $startDate, mixed $endDate, bool $includeForecast): array
    {
        $title = $view === 'comparison' ? 'Actual vs. Forecast' : 'Historical Production';
        $startDate = is_string($startDate) ? $startDate : null;
        $endDate = is_string($endDate) ? $endDate : null;

        if ($startDate === null || $startDate === '' || $endDate === null || $endDate === '') {
            $end = Carbon::yesterday()->startOfDay();
            $start = $end->copy()->subDays(29);
            $startDate = $start->toDateString();
            $endDate = $end->toDateString();
        }

        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->startOfDay();
        } catch (Throwable) {
            return $this->emptyPayload(
                $view,
                $title,
                true,
                null,
                null,
                'Choose a valid start and end date.'
            );
        }

        if ($start->gt($end)) {
            return $this->emptyPayload(
                $view,
                $title,
                true,
                $start->toDateString(),
                $end->toDateString(),
                'The start date has to be on or before the end date.'
            );
        }

        if ($start->copy()->addDays(self::MAX_RANGE_DAYS - 1)->lt($end)) {
            return $this->emptyPayload(
                $view,
                $title,
                true,
                $start->toDateString(),
                $end->toDateString(),
                'Choose a range of '.self::MAX_RANGE_DAYS.' days or fewer.'
            );
        }

        $range = $this->trends->forDateRange(
            $start->toDateString(),
            $end->toDateString(),
            $includeForecast
        );

        $datasets = [];
        $hasActual = false;
        $hasForecast = false;

        foreach ($range['series'] as $series) {
            $isForecast = ($series['label'] ?? '') === 'Forecast';
            if ($isForecast && ! $includeForecast) {
                continue;
            }

            $data = array_values($series['data'] ?? []);
            $datasets[] = [
                'label' => (string) ($series['label'] ?? 'Production'),
                'data' => $data,
                'forecast' => $isForecast,
            ];

            foreach ($data as $value) {
                if ($value === null || ! is_numeric($value)) {
                    continue;
                }
                if ($isForecast) {
                    $hasForecast = true;
                } else {
                    $hasActual = true;
                }
            }
        }

        $period = $title.' · '.$this->formatPeriod($start, $end);

        if (! $hasActual && ! $hasForecast) {
            return $this->emptyPayload(
                $view,
                $period,
                true,
                $start->toDateString(),
                $end->toDateString(),
                $includeForecast
                    ? 'No actual production or forecast values in this range.'
                    : 'No egg production records in this range.'
            );
        }

        $notice = null;
        if ($includeForecast && $hasActual && ! $hasForecast) {
            $datasets = array_values(array_filter(
                $datasets,
                fn (array $dataset) => ! $dataset['forecast']
            ));
            $notice = 'No forecast could be made for these dates. The chart shows actual production only.';
        } elseif ($includeForecast && $hasForecast) {
            $notice = 'ARIMA(2,1,1) seven-day-ahead: each week is predicted only from harvests before that week.';
        }

        if (! $hasActual) {
            $datasets = array_values(array_filter(
                $datasets,
                fn (array $dataset) => ($dataset['label'] ?? '') !== 'Actual Production'
            ));
        }

        return [
            'view' => $view,
            'period' => $period,
            'needsDates' => true,
            'rangeLocked' => false,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'emptyMessage' => null,
            'notice' => $notice,
            'labels' => array_values($range['labels'] ?? []),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return array{
     *     view: string,
     *     period: string,
     *     needsDates: bool,
     *     rangeLocked: bool,
     *     startDate: ?string,
     *     endDate: ?string,
     *     emptyMessage: ?string,
     *     notice: ?string,
     *     labels: list<string>,
     *     datasets: list<array{label: string, data: list<int|float|null>, forecast: bool}>
     * }
     */
    private function forward(string $view, int $days, ?Carbon $startFrom = null): array
    {
        $origin = ($startFrom ?? Carbon::tomorrow())->copy()->startOfDay();
        $rows = Cache::remember(
            'egg-production-chart:'.$view.':'.$origin->toDateString(),
            now()->addMinutes(5),
            fn () => $this->forecasts->upcomingDays($days, $origin)
        );

        $labels = [];
        $data = [];
        $hasValue = false;

        foreach ($rows as $row) {
            $date = Carbon::parse($row['date'])->startOfDay();
            $labels[] = $date->format('M j');
            $eggs = is_numeric($row['eggs'] ?? null) ? (int) $row['eggs'] : 0;
            // Zero is "no model result", not a harvest of zero. Leave a gap the line can span.
            $data[] = $eggs > 0 ? $eggs : null;
            if ($eggs > 0) {
                $hasValue = true;
            }
        }

        $start = isset($rows[0]['date'])
            ? Carbon::parse($rows[0]['date'])->startOfDay()
            : $origin->copy();
        $end = isset($rows[array_key_last($rows)]['date'])
            ? Carbon::parse($rows[array_key_last($rows)]['date'])->startOfDay()
            : $start->copy()->addDays($days - 1);
        $title = $view === 'timeseries' ? 'Time Series Forecast' : 'Seven-Day Forecast';
        $period = $title.' · '.$this->formatPeriod($start, $end);

        if (! $hasValue) {
            return $this->emptyPayload(
                $view,
                $period,
                false,
                $start->toDateString(),
                $end->toDateString(),
                'The forecast is unavailable right now.'
            );
        }

        return [
            'view' => $view,
            'period' => $period,
            'needsDates' => false,
            'rangeLocked' => false,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'emptyMessage' => null,
            'notice' => null,
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Forecast',
                    'data' => $data,
                    'forecast' => true,
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{label: string, data: list<int|float|null>, forecast: bool}>  $datasets
     * @return array{
     *     view: string,
     *     period: string,
     *     needsDates: bool,
     *     rangeLocked: bool,
     *     startDate: ?string,
     *     endDate: ?string,
     *     emptyMessage: ?string,
     *     notice: ?string,
     *     labels: list<string>,
     *     datasets: list<array{label: string, data: list<int|float|null>, forecast: bool}>
     * }
     */
    private function emptyPayload(
        string $view,
        string $period,
        bool $needsDates,
        ?string $startDate,
        ?string $endDate,
        string $message,
    ): array {
        return [
            'view' => $view,
            'period' => $period,
            'needsDates' => $needsDates,
            'rangeLocked' => false,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'emptyMessage' => $message,
            'notice' => null,
            'labels' => [],
            'datasets' => [],
        ];
    }

    private function formatPeriod(Carbon $start, Carbon $end): string
    {
        if ($start->equalTo($end)) {
            return $start->format('M j, Y');
        }

        if ($start->year === $end->year) {
            return $start->format('M j').' – '.$end->format('M j, Y');
        }

        return $start->format('M j, Y').' – '.$end->format('M j, Y');
    }
}
