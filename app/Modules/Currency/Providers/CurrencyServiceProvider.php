<?php

namespace App\Modules\Currency\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Currency\Services\CurrencyConversionService;
use App\Modules\Currency\Console\CurrencySyncCommand;

class CurrencyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrencyConversionService::class, function ($app) {
            return new CurrencyConversionService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CurrencySyncCommand::class,
            ]);
        }
    }
}
