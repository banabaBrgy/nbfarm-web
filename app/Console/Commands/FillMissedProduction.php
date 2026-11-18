<?php

namespace App\Console\Commands;

use App\Services\MissedProductionFill;
use Illuminate\Console\Command;

class FillMissedProduction extends Command
{
    protected $signature = 'production:fill-missed';

    protected $description = 'Insert production for past days with no harvest, kept within one tray of that day\'s forecast';

    public function handle(MissedProductionFill $fill): int
    {
        $result = $fill->fill();

        if ($result['days'] === 0) {
            $this->info('No missed production days to fill.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Inserted production for %d missed day(s), %s through %s. Each total is within one tray of that day\'s forecast.',
            $result['days'],
            $result['start'],
            $result['end']
        ));

        return self::SUCCESS;
    }
}
