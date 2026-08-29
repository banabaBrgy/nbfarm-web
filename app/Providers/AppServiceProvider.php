<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
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

        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()   
                ->numbers()    
                ->symbols();    
        });
    }
}