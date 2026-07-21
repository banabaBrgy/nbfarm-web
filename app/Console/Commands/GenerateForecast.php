<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ForecastingService;

class GenerateForecast extends Command
{
    protected $signature = 'forecast:daily';

    protected $description = 'Generate daily egg production forecast';

    public function handle(ForecastingService $service)
    {
        $forecast = $service->generateDailyForecast();

        $this->info('Forecast Generated: '.$forecast);

        return self::SUCCESS;
    }
}