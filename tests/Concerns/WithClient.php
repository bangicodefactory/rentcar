<?php

namespace Tests\Concerns;

trait WithClient
{
    protected function asClient(string $client): static
    {
        config(['app.client' => $client]);

        // Re-run the ClientServiceProvider merge so config('client.*') reflects the new client.
        $this->app->register(\App\Providers\ClientServiceProvider::class, true);

        return $this;
    }
}
