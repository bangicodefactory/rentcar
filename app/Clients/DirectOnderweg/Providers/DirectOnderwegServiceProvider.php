<?php

namespace App\Clients\DirectOnderweg\Providers;

use App\Clients\DirectOnderweg\Services\DirectOnderwegPricingService;
use App\Clients\DirectOnderweg\Services\DirectOnderwegTvaService;
use App\Contracts\PricingServiceContract;
use App\Contracts\TvaServiceContract;
use Illuminate\Support\ServiceProvider;

class DirectOnderwegServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ClientServiceProvider already binds these via the config 'bindings' array.
        // Re-binding here gives this provider a single place for future per-client
        // boot() logic (cache warm-up, event listeners, etc.). The config bindings
        // act as a safety net if this provider fails to load.
        $this->app->bind(PricingServiceContract::class, DirectOnderwegPricingService::class);
        $this->app->bind(TvaServiceContract::class, DirectOnderwegTvaService::class);
    }
}
