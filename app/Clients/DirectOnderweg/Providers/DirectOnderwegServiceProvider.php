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
        $this->app->bind(PricingServiceContract::class, DirectOnderwegPricingService::class);
        $this->app->bind(TvaServiceContract::class, DirectOnderwegTvaService::class);
    }
}
