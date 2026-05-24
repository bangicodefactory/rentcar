<?php

namespace App\Providers;

use App\Contracts\PricingServiceContract;
use App\Contracts\TvaServiceContract;
use App\Services\DefaultPricingService;
use App\Services\DefaultTvaService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Fallback bindings — overridden by ClientServiceProvider when a
        // client config supplies its own concrete class.
        $this->app->bind(PricingServiceContract::class, DefaultPricingService::class);
        $this->app->bind(TvaServiceContract::class, DefaultTvaService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
            Schema::defaultStringLength(191);
            config(['default_terms' => require config_path('default_terms.php')]);

    }
}
