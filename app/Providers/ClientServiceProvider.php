<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $client  = config('app.client', 'directonderweg');
        $default = config('clients._default', []);
        $specific = config("clients.{$client}", []);

        // Client keys win over defaults.
        $resolved = array_replace_recursive($default, $specific);
        config(['client' => $resolved]);

        // Bind client-specific implementations to core interfaces.
        foreach ($resolved['bindings'] ?? [] as $contract => $concrete) {
            $this->app->bind($contract, $concrete);
        }

        // Dynamically register the active client's own ServiceProvider if it exists.
        $clientProvider = sprintf(
            'App\\Clients\\%s\\Providers\\%sServiceProvider',
            Str::studly($client),
            Str::studly($client)
        );
        if (class_exists($clientProvider)) {
            $this->app->register($clientProvider);
        }
    }
}
