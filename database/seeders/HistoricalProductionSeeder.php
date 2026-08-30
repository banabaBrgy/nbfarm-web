<?php

namespace Database\Seeders;

use App\Models\Production;
use Carbon\Carbon;
use Database\Seeders\Concerns\SplitsEggSizes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class HistoricalProductionSeeder extends Seeder
{
    use SplitsEggSizes;

    /**
     * Store 3 prior years of harvest in the DB only.
     * Live production table / egg stock stay on the present year.
     */
    public function run(): void
    {
        $start = now()->copy()->subYears(3)->startOfYear();
        $end = now()->copy()->subYear()->endOfYear();

        Production::withoutEvents(function () use ($start, $end) {
            Production::query()
                ->whereDate('production_date', '>=', $start->toDateString())
                ->whereDate('production_date', '<=', $end->toDateString())
                ->delete();

            $rows = [];
            $cursor = $start->copy();

            while ($cursor->lte($end)) {
                $sizes = $this->splitBySize($this->dailyTotal($cursor));

                $rows[] = [
                    'production_date' => $cursor->toDateString(),
                    'batch_id' => 1,
                    'small_eggs' => $sizes['small'],
                    'medium_eggs' => $sizes['medium'],
                    'large_eggs' => $sizes['large'],
                    'extra_large_eggs' => $sizes['extra_large'],
                    'cracked_eggs' => $sizes['cracked'],
                    'eggs_produced' => $sizes['produced'],
                ];

                if (count($rows) >= 200) {
                    Production::insert($rows);
                    $rows = [];
                }

                $cursor->addDay();
            }

            if ($rows !== []) {
                Production::insert($rows);
            }
        });

        $this->command?->info(
            "Stored archive harvest {$start->toDateString()} → {$end->toDateString()} (hidden from present-year screens)."
        );

        Cache::flush();
    }

    /** Deterministic daily total, multiple of 30. */
    private function dailyTotal(Carbon $date): int
    {
        $wave = (int) (round(sin($date->dayOfYear / 365 * M_PI * 2) * 240 / 30) * 30);
        $weekdayBoost = [0, 60, 90, 120, 150, 180, 90][$date->dayOfWeek] ?? 0;

        return 4200 + $wave + $weekdayBoost;
    }
}
