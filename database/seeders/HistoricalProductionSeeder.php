<?php

namespace Database\Seeders;

use App\Models\Production;
use App\Models\QrTransaction;
use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsHarvestQr;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class HistoricalProductionSeeder extends Seeder
{
    use SeedsHarvestQr;

    /**
     * Year 1 = 2024, Year 2 = 2025. Dummy harvest + matching QR scans.
     * Also removes leftover 2023 archive rows from the older seeder.
     */
    public function run(): void
    {
        $year1Start = Carbon::parse('2024-01-01')->startOfDay();
        $year2End = Carbon::parse('2025-12-31')->startOfDay();

        Production::withoutEvents(function () {
            $staleIds = Production::query()
                ->whereYear('production_date', 2023)
                ->pluck('production_id');

            if ($staleIds->isNotEmpty()) {
                QrTransaction::whereIn('production_id', $staleIds)->delete();
                Production::whereIn('production_id', $staleIds)->delete();
            }
        });

        $days = $this->seedHarvestRange(
            $year1Start,
            $year2End,
            fn (Carbon $date) => $this->dailyWaveTotal($date)
        );

        Cache::flush();

        $this->command?->info(
            "Year 1 (2024) + Year 2 (2025): {$days} harvest days with dummy QR scans."
        );
    }
}
