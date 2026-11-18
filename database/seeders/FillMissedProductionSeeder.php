<?php

namespace Database\Seeders;

use App\Services\MissedProductionFill;
use Illuminate\Database\Seeder;

class FillMissedProductionSeeder extends Seeder
{
    /**
     * Past days the client PDF does not cover get a harvest near that day's forecast.
     */
    public function run(): void
    {
        $result = app(MissedProductionFill::class)->fill();

        $this->command?->info(sprintf(
            'Missed production days filled: %d.',
            $result['days']
        ));
    }
}
