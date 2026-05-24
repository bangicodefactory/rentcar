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
    'paypal'       => env('FEATURE_PAYPAL', null),
    'stripe'       => env('FEATURE_STRIPE', null),
    'excel_import' => env('FEATURE_EXCEL_IMPORT', null),
    'multi_branch' => env('FEATURE_MULTI_BRANCH', null),
    'tva_renumber' => env('FEATURE_TVA_RENUMBER', null),
    'signatures'   => env('FEATURE_SIGNATURES', null),
];
