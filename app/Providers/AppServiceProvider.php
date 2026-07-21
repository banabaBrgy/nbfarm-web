<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Production;
use App\Observers\ProductionObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Production::observe(ProductionObserver::class);
    }
}