<?php

return [

    /*
     * Feature-flag defaults. Every client inherits these; per-client
     * files override individual keys via array_replace_recursive().
     * Set to today's behavior so existing deploys are unchanged.
     */
    'features' => [
        'paypal'       => true,
        'stripe'       => true,
        'excel_import' => true,
        'multi_branch' => false,
        'tva_renumber' => true,
        'signatures'   => true,
    ],

    /*
     * Interface → concrete bindings resolved by ClientServiceProvider.
     * Core code injects the interface; each client supplies the class.
     */
    'bindings' => [],
];
