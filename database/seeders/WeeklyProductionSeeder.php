<?php

namespace Database\Seeders;

use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WeeklyProductionSeeder extends Seeder
{
    /** Demo batch ID — does not overwrite real batch 1/2 QR production. */
    private const DEMO_BATCH_ID = 99;

    /**
     * Daily totals for the last 7 days (oldest → today).
     */
    private array $dailyTotals = [
        1100,
        1450,
        980,
        2200,
        1750,
        1900,
        600,
    ];

    public function run(): void
    {
        $start = Carbon::today()->subDays(6)->toDateString();
        $end = Carbon::today()->toDateString();

        Production::where('batch_id', self::DEMO_BATCH_ID)
            ->whereBetween('production_date', [$start, $end])
            ->delete();

        foreach ($this->dailyTotals as $index => $total) {
            $date = Carbon::today()->subDays(6 - $index);
            $sizes = $this->splitBySize($total);

            Production::create([
                'production_date' => $date->toDateString(),
                'batch_id' => self::DEMO_BATCH_ID,
                'small_eggs' => $sizes['small'],
                'medium_eggs' => $sizes['medium'],
                'large_eggs' => $sizes['large'],
                'extra_large_eggs' => $sizes['extra_large'],
                'cracked_eggs' => $sizes['cracked'],
                'eggs_produced' => $sizes['produced'],
            ]);
        }

        $this->command?->info('Seeded demo weekly production for the last 7 days (batch 99).');
        $this->command?->info("Date range: {$start} → {$end}");
    }

    private function splitBySize(int $total): array
    {
        $cracked = max(0, (int) round($total * 0.03));
        $good = $total - $cracked;

        $small = (int) round($good * 0.25);
        $medium = (int) round($good * 0.35);
        $large = (int) round($good * 0.28);
        $extraLarge = max(0, $good - $small - $medium - $large);

        return [
            'small' => $small,
            'medium' => $medium,
            'large' => $large,
            'extra_large' => $extraLarge,
            'cracked' => $cracked,
            'produced' => $small + $medium + $large + $extraLarge,
        ];
    }
}
