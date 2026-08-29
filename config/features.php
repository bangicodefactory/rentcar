<?php

/*
 * Feature-flag env overrides.
 *
 * Resolution order (highest precedence first):
 *   1. FEATURE_* env var (emergency override via .env or GitHub Environment)
 *   2. config/clients/<client>.php features array  (set by ClientServiceProvider)
 *   3. config/clients/_default.php features array
 *
 * A null value here means "no env override — fall through to client config".
 * Use feature('name') anywhere; never read this file directly.
 */
return [
    'paypal'           => env('FEATURE_PAYPAL', null),
    'stripe'           => env('FEATURE_STRIPE', null),
    'subscriptions'    => env('FEATURE_SUBSCRIPTIONS', null),
    'booking_payment'  => env('FEATURE_BOOKING_PAYMENT', null),
    'excel_import'     => env('FEATURE_EXCEL_IMPORT', null),
    'multi_branch'     => env('FEATURE_MULTI_BRANCH', null),
    'tva_renumber'     => env('FEATURE_TVA_RENUMBER', null),
    'signatures'       => env('FEATURE_SIGNATURES', null),
    'cash_split'       => env('FEATURE_CASH_SPLIT', null),
    'invoice_on_full_payment' => env('FEATURE_INVOICE_ON_FULL_PAYMENT', null),
    'traffic_violations' => env('FEATURE_TRAFFIC_VIOLATIONS', null),
    'public_storefront' => env('FEATURE_PUBLIC_STOREFRONT', null),
];
