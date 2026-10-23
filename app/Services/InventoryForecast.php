<?php

namespace App\Services;

use App\Models\Production;
use App\Services\Forecasting\GenerateEggForecast;
use Carbon\Carbon;

class InventoryForecast
{
    public function __construct(private GenerateEggForecast $eggs) {}

    /**
     * Next 7 days, starting tomorrow. Today's deduction has already run.
     *
     * @return array{
     *     supplies: list<array{
     *         key: string,
     *         item: string,
     *         unit: string,
     *         daily_use: float|null,
     *         consumption: float,
     *         stock_after: float|null,
     *         resets_daily: bool,
     *         already_at_minimum: bool,
     *         reaches_minimum_on: string|null
     *     }>,
     *     trays: list<array{
     *         date: string,
     *         eggs: int,
     *         trays_needed: int,
     *         trays_left: float,
     *         below_minimum: bool
     *     }>
     * }
     */
    public function summary(?object $feed, ?object $vitamins, ?object $trays): array
    {
        return $this->fromEggs($feed, $vitamins, $trays, $this->eggs->upcomingDays(7));
    }

    /**
     * @param  list<array{date: string, eggs: int}>  $days
     * @return array{
     *     supplies: list<array{
     *         key: string,
     *         item: string,
     *         unit: string,
     *         daily_use: float|null,
     *         consumption: float,
     *         stock_after: float|null,
     *         resets_daily: bool,
     *         already_at_minimum: bool,
     *         reaches_minimum_on: string|null
     *     }>,
     *     trays: list<array{
     *         date: string,
     *         eggs: int,
     *         trays_needed: int,
     *         trays_left: float,
     *         below_minimum: bool
     *     }>
     * }
     */
    public function fromEggs(?object $feed, ?object $vitamins, ?object $trays, array $days): array
    {
        $start = Carbon::tomorrow()->startOfDay();
        $trayDays = $this->trayDays($trays, $days);
        $trayUse = array_sum(array_column($trayDays, 'trays_needed'));

        return [
            'supplies' => [
                $this->supply('feeds', 'Feeds', $feed, DailyInventory::FEED_DAILY_USE, 'sacks', $start),
                $this->supply('vitamins', 'Vitamins', $vitamins, DailyInventory::VITAMIN_DAILY_USE, 'bottles', $start),
                [
                    'key' => 'trays',
                    'item' => 'Egg Trays',
                    'unit' => $this->unit($trays, 'pcs'),
                    'daily_use' => null,
                    'consumption' => (float) $trayUse,
                    'stock_after' => null,
                    'resets_daily' => true,
                    'already_at_minimum' => false,
                    'reaches_minimum_on' => null,
                ],
            ],
            'trays' => $trayDays,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     item: string,
     *     unit: string,
     *     daily_use: float,
     *     consumption: float,
     *     stock_after: float,
     *     resets_daily: bool,
     *     already_at_minimum: bool,
     *     reaches_minimum_on: string|null
     * }
     */
    private function supply(string $key, string $name, ?object $item, float $daily, string $fallbackUnit, Carbon $start): array
    {
        $current = (float) ($item->current_stock ?? 0);
        $min = (float) ($item->min_level ?? 0);
        $hit = $this->minimumHit($current, $min, $daily, $start);

        return [
            'key' => $key,
            'item' => $name,
            'unit' => $this->unit($item, $fallbackUnit),
            'daily_use' => $daily,
            'consumption' => $daily * 7,
            'stock_after' => $current - ($daily * 7),
            'resets_daily' => false,
            'already_at_minimum' => $hit['already'],
            'reaches_minimum_on' => $hit['date'],
        ];
    }

    /**
     * @return array{already: bool, date: string|null}
     */
    private function minimumHit(float $current, float $min, float $daily, Carbon $start): array
    {
        if ($daily <= 0) {
            return ['already' => false, 'date' => null];
        }

        if ($current <= $min) {
            return ['already' => true, 'date' => null];
        }

        for ($day = 1; $day <= 7; $day++) {
            if ($current - ($daily * $day) <= $min) {
                return [
                    'already' => false,
                    'date' => $start->copy()->addDays($day - 1)->toDateString(),
                ];
            }
        }

        return ['already' => false, 'date' => null];
    }

    /**
     * Each morning the tray pool resets, then that day's harvest uses eggs / 30.
     *
     * @param  list<array{date: string, eggs: int}>  $days
     * @return list<array{date: string, eggs: int, trays_needed: int, trays_left: float, below_minimum: bool}>
     */
    private function trayDays(?object $trays, array $days): array
    {
        $pool = $trays !== null && $trays->daily_stock !== null
            ? (float) $trays->daily_stock
            : (float) DailyInventory::TRAY_DAILY_STOCK;
        $min = (float) ($trays->min_level ?? 0);
        $rows = [];

        foreach ($days as $day) {
            $eggs = (int) ($day['eggs'] ?? 0);
            $needed = intdiv($eggs, Production::EGGS_PER_TRAY);
            $left = $pool - $needed;
            $rows[] = [
                'date' => (string) $day['date'],
                'eggs' => $eggs,
                'trays_needed' => $needed,
                'trays_left' => $left,
                'below_minimum' => $left <= $min,
            ];
        }

        return $rows;
    }

    private function unit(?object $item, string $fallback): string
    {
        $unit = trim((string) ($item->unit ?? ''));

        return $unit !== '' ? $unit : $fallback;
    }
}
