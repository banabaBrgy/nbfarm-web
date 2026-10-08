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
     * Client harvest for 2023–2026. Each year comes from the farm PDF.
     */
    public function run(): void
    {
        $count = $this->seedClientFile();

        Cache::flush();

        $this->command?->info("Client harvest 2023–2026: {$count} days loaded from the updated PDFs.");
    }

    public function seedClientFile(?int $year = null): int
    {
        $path = database_path('seeders/data/client_egg_production.json');
        $days = json_decode((string) file_get_contents($path), true);

        if (! is_array($days) || $days === []) {
            throw new RuntimeException('Client egg production file is missing or empty.');
        }

        if ($year !== null) {
            $prefix = $year.'-';
            $days = array_values(array_filter(
                $days,
                fn (array $day): bool => str_starts_with((string) $day['date'], $prefix)
            ));
        }

        if ($days === []) {
            throw new RuntimeException('No client harvest days matched the requested year.');
        }

        return $this->seedClientDays($days);
    }
}
