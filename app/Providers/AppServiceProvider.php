<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Models\Production;
use App\Observers\ProductionObserver;
use App\Services\Forecasting\ForecastEngine;
use App\Services\Forecasting\PythonForecastEngine;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ForecastEngine::class, PythonForecastEngine::class);
    }

    public function boot(): void
    {
        Production::observe(ProductionObserver::class);

        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()   
                ->numbers()    
                ->symbols();    
        });
    }
}