<?php

namespace App\Console\Commands;

use App\Services\Forecasting\GenerateEggForecast;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateForecast extends Command
{
    protected $signature = 'forecast:daily';

    protected $description = 'Generate daily egg production forecast via the Python API';

    public function handle(GenerateEggForecast $service)
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $result = $service->forWeek($weekStart, $weekStart->copy()->addDays(6));

        $this->info('Forecast Generated: '.$result['predicted_eggs']);

        return self::SUCCESS;
    }
}
