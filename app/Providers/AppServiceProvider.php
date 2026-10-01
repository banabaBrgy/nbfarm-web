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
        $this->ensureOpensslConfig();

        Production::observe(ProductionObserver::class);

        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()   
                ->numbers()    
                ->symbols();    
        });
    }

    private function ensureOpensslConfig(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || getenv('OPENSSL_CONF')) {
            return;
        }

        $ini = php_ini_loaded_file();
        $guesses = [];

        if (is_string($ini) && $ini !== '') {
            $dir = dirname($ini);
            $guesses[] = $dir.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';
            $guesses[] = $dir.DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'openssl'.DIRECTORY_SEPARATOR.'openssl.cnf';
        }

        $guesses[] = 'C:\\xampp\\php\\extras\\ssl\\openssl.cnf';

        foreach ($guesses as $cnf) {
            try {
                if (@is_file($cnf)) {
                    putenv('OPENSSL_CONF='.$cnf);
                    return;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }
}