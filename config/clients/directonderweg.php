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
     * Interface → concrete bindings.
     * Populated once per-client service classes exist (Phase 2+).
     */
    'bindings' => [],

    'branding_seed' => [
        'app_name'       => 'Direct Onderweg',
        'theme_color'    => 'color1',
        'company_logo'   => 'logo.png',
        'meta_seo_title' => 'Direct Onderweg — Car Rental',
    ],
];
