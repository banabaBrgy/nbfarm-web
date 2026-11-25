<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

class DashboardDateFilter
{
    public const PERIOD_TODAY = 'today';

    public const PERIOD_DATE = 'date';

    public const PERIOD_RANGE = 'range';

    public const QUERY_PERIOD = 'dash_period';

    public const QUERY_DATE = 'dash_date';

    public const QUERY_FROM = 'dash_from';

    public const QUERY_TO = 'dash_to';

    public const MAX_RANGE_DAYS = 366;

    public function __construct(
        public readonly string $period,
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            self::QUERY_PERIOD => 'nullable|in:today,date,range',
            self::QUERY_DATE => 'nullable|date|before_or_equal:today',
            self::QUERY_FROM => 'nullable|required_with:'.self::QUERY_TO.'|date',
            self::QUERY_TO => [
                'nullable',
                'required_with:'.self::QUERY_FROM,
                'date',
                'after_or_equal:'.self::QUERY_FROM,
                'before_or_equal:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $from = request()->input(self::QUERY_FROM);
                    if (! is_string($from) || $from === '' || ! is_string($value) || $value === '') {
                        return;
                    }

                    try {
                        $start = Carbon::parse($from)->startOfDay();
                        $end = Carbon::parse($value)->startOfDay();
                    } catch (Throwable) {
                        return;
                    }

                    if ($start->copy()->addDays(self::MAX_RANGE_DAYS - 1)->lt($end)) {
                        $fail('Choose a range of '.self::MAX_RANGE_DAYS.' days or fewer.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function chartRules(): array
    {
        return [
            'start_date' => 'nullable|required_with:end_date|date|before_or_equal:today',
            'end_date' => 'nullable|required_with:start_date|date|after_or_equal:start_date|before_or_equal:today',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function effectivenessRules(): array
    {
        return [
            'effectiveness_from' => 'nullable|required_with:effectiveness_to|date',
            'effectiveness_to' => [
                'nullable',
                'required_with:effectiveness_from',
                'date',
                'after_or_equal:effectiveness_from',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $from = request()->input('effectiveness_from');
                    if (! is_string($from) || $from === '' || ! is_string($value) || $value === '') {
                        return;
                    }

                    try {
                        $start = Carbon::parse($from)->startOfDay();
                        $end = Carbon::parse($value)->startOfDay();
                    } catch (Throwable) {
                        return;
                    }

                    if ($start->copy()->addDays(self::MAX_RANGE_DAYS - 1)->lt($end)) {
                        $fail('Choose a range of '.self::MAX_RANGE_DAYS.' days or fewer.');
                    }
                },
            ],
        ];
    }

    public static function fromRequest(Request $request): self
    {
        $today = Carbon::today()->startOfDay();
        $period = $request->input(self::QUERY_PERIOD, self::PERIOD_TODAY);
        $period = is_string($period) ? $period : self::PERIOD_TODAY;

        if (! in_array($period, [self::PERIOD_TODAY, self::PERIOD_DATE, self::PERIOD_RANGE], true)) {
            $period = self::PERIOD_TODAY;
        }

        try {
            if ($request->filled(self::QUERY_FROM) && $request->filled(self::QUERY_TO)) {
                return self::between(
                    $request->input(self::QUERY_FROM),
                    $request->input(self::QUERY_TO),
                    $today,
                );
            }

            if ($period === self::PERIOD_DATE && $request->filled(self::QUERY_DATE)) {
                $date = Carbon::parse($request->input(self::QUERY_DATE))->startOfDay();
                if ($date->gt($today) || $date->equalTo($today)) {
                    return new self(self::PERIOD_TODAY, $today, $today->copy());
                }

                return new self(self::PERIOD_DATE, $date, $date->copy());
            }
        } catch (Throwable) {
            // Incomplete or invalid input falls back to today.
        }

        return new self(self::PERIOD_TODAY, $today, $today->copy());
    }

    private static function between(mixed $from, mixed $to, Carbon $today): self
    {
        $start = Carbon::parse(is_string($from) ? $from : '')->startOfDay();
        $end = Carbon::parse(is_string($to) ? $to : '')->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($end->gt($today)) {
            $end = $today->copy();
        }

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        if ($start->copy()->addDays(self::MAX_RANGE_DAYS - 1)->lt($end)) {
            $start = $end->copy()->subDays(self::MAX_RANGE_DAYS - 1);
        }

        if ($start->equalTo($end)) {
            if ($start->equalTo($today)) {
                return new self(self::PERIOD_TODAY, $today, $today->copy());
            }

            return new self(self::PERIOD_DATE, $start, $end);
        }

        return new self(self::PERIOD_RANGE, $start, $end);
    }

    public function isToday(): bool
    {
        $today = Carbon::today();

        return $this->start->isSameDay($today) && $this->end->isSameDay($today);
    }

    public function isRange(): bool
    {
        return $this->period === self::PERIOD_RANGE;
    }

    public function asOf(): Carbon
    {
        return $this->end->copy();
    }

    public function forecastStart(): Carbon
    {
        return $this->asOf()->addDay();
    }

    public function startDate(): string
    {
        return $this->start->toDateString();
    }

    public function endDate(): string
    {
        return $this->end->toDateString();
    }

    /**
     * Equal-length window immediately before this filter.
     * Example: Nov 1–17 → Oct 15–31; a single day → the day before.
     *
     * @return array{0: string, 1: string}
     */
    public function previousWindow(): array
    {
        $length = (int) $this->start->diffInDays($this->end);
        $previousEnd = $this->start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($length);

        return [
            $previousStart->toDateString(),
            $previousEnd->toDateString(),
        ];
    }

    public function previousAsOf(): Carbon
    {
        return $this->start->copy()->subDay();
    }

    public function subtitle(): string
    {
        if ($this->isToday()) {
            return "Welcome back! Here's today's poultry farm overview.";
        }

        if ($this->isRange()) {
            return 'Farm overview for '.$this->formatPeriod().'.';
        }

        return 'Farm overview for '.$this->start->format('F j, Y').'.';
    }

    public function formatPeriod(): string
    {
        if ($this->start->equalTo($this->end)) {
            return $this->start->format('M j, Y');
        }

        if ($this->start->year === $this->end->year) {
            return $this->start->format('M j').' – '.$this->end->format('M j, Y');
        }

        return $this->start->format('M j, Y').' – '.$this->end->format('M j, Y');
    }

    /**
     * @return array{
     *     period: string,
     *     date: string,
     *     from: string,
     *     to: string,
     *     isToday: bool,
     *     isRange: bool,
     *     maxDate: string,
     *     asOfLabel: string
     * }
     */
    public function toView(): array
    {
        return [
            'period' => $this->period,
            'date' => $this->startDate(),
            'from' => $this->startDate(),
            'to' => $this->endDate(),
            'isToday' => $this->isToday(),
            'isRange' => $this->isRange(),
            'maxDate' => Carbon::today()->toDateString(),
            'asOfLabel' => $this->end->format('M j, Y'),
        ];
    }
}
