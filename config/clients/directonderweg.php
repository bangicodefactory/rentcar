<?php

return [

    'name'               => 'Direct Onderweg',
    'default_locale'     => 'nl',
    'supported_locales'  => ['nl', 'fr', 'en', 'ar'],

    'features' => [
        'paypal'       => true,
        'stripe'       => true,
        'excel_import' => true,
        'multi_branch' => false,
        'tva_renumber' => true,
        'signatures'   => true,
    ],

    /*
     * Explicit provider class — required because Str::studly('directonderweg')
     * produces 'Directonderweg', not 'DirectOnderweg'.
     */
    'provider_class' => \App\Clients\DirectOnderweg\Providers\DirectOnderwegServiceProvider::class,

    /*
     * Interface → concrete bindings resolved by ClientServiceProvider.
     * Also re-bound inside DirectOnderwegServiceProvider for explicitness.
     */
    'bindings' => [
        \App\Contracts\PricingServiceContract::class
            => \App\Clients\DirectOnderweg\Services\DirectOnderwegPricingService::class,
        \App\Contracts\TvaServiceContract::class
            => \App\Clients\DirectOnderweg\Services\DirectOnderwegTvaService::class,
    ],

    'branding_seed' => [
        'app_name'       => 'Direct Onderweg',
        'theme_color'    => 'color1',
        'company_logo'   => 'logo.png',
        'meta_seo_title' => 'Direct Onderweg — Car Rental',
    ],
];
