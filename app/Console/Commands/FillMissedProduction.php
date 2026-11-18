<?php

namespace App\Console\Commands;

use App\Services\MissedProductionFill;
use Illuminate\Console\Command;

class FillMissedProduction extends Command
{
    protected $signature = 'production:fill-missed
        {--align-year= : Rewrite this year\'s harvest days that sit more than one tray from the forecast}
        {--lower-high= : Lower only this many days that sit farthest above the forecast}';

    protected $description = 'Insert production for past days with no harvest, kept within one tray of that day\'s forecast';

    public function handle(MissedProductionFill $fill): int
    {
        $alignYear = $this->option('align-year');
        $lowerHigh = $this->option('lower-high');

        if (is_numeric($lowerHigh)) {
            $result = $fill->lowerHighDays((int) $lowerHigh);

            if ($result['days'] === 0) {
                $this->info('No harvest days sat far above the forecast.');

                return self::SUCCESS;
            }

            $this->info(sprintf(
                'Lowered %d high actual day(s) toward the forecast: %s.',
                $result['days'],
                implode(', ', $result['dates'])
            ));

            return self::SUCCESS;
        }

        $result = is_numeric($alignYear)
            ? $fill->alignYear((int) $alignYear)
            : $fill->fill();

        if ($result['days'] === 0) {
            $this->info(is_numeric($alignYear)
                ? 'No '.$alignYear.' harvest days needed to be pulled toward the forecast.'
                : 'No missed production days to fill.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            is_numeric($alignYear)
                ? 'Aligned %d day(s) in %s, %s through %s. Each total is within one tray of that day\'s forecast.'
                : 'Inserted production for %d missed day(s), %s through %s. Each total is within one tray of that day\'s forecast.',
            $result['days'],
            ...(is_numeric($alignYear)
                ? [$alignYear, $result['start'], $result['end']]
                : [$result['start'], $result['end']])
        ));

        return self::SUCCESS;
    }
}
