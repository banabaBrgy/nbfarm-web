<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsHarvestQr;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class HistoricalProductionSeeder extends Seeder
{
    use SeedsHarvestQr;

    /**
     * Client harvest for 2023, 2024, and 2025 (daily batch and size totals).
     * 2026 stays on the weekly seeder.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/client_egg_production.json');
        $days = json_decode((string) file_get_contents($path), true);

        if (! is_array($days) || $days === []) {
            throw new RuntimeException('Client egg production file is missing or empty.');
        }

        $count = $this->seedClientDays($days);

        Cache::flush();

        $this->command?->info("Client harvest 2023–2025: {$count} days loaded from the updated PDFs.");
    }
}
