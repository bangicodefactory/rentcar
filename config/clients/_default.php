<?php

return [

    /*
     * Feature-flag defaults. Every client inherits these; per-client
     * files override individual keys via array_replace_recursive().
     * Set to today's behavior so existing deploys are unchanged.
     */
    'features' => [
        'paypal'          => true,
        'stripe'          => true,
        'subscriptions'   => true,
        'booking_payment' => true,
        'excel_import'    => true,
        'multi_branch'    => false,
        'tva_renumber'    => true,
        'signatures'      => true,
        // Split a cash payment over `cash_payment_max` into several receipts
        // each within the cap (Moroccan CGI art. 193 per-day cash ceiling),
        // instead of rejecting it. Off = today's behavior (reject).
        'cash_split'      => false,
        // Defer invoice (facture) creation until a booking is fully paid, then
        // emit one per payment. Off = today's behavior (one invoice per payment,
        // including partial payments).
        'invoice_on_full_payment' => false,
        // Traffic violation (contravention / PV) tracking: record a notice and
        // match it to the booking + renter that held the vehicle at that
        // instant. Off by default so an unconfigured client is unchanged.
        'traffic_violations' => false,
        // Public B2C rental storefront: /landing (fleet + booking widget),
        // /contact, /search, /newsletter/subscribe. On by default because that
        // is today's behavior for every existing client (§10.2 rule 2). Turn it
        // off for clients whose public face is not a rental storefront.
        'public_storefront' => true,
    ],

    /*
     * Server-rendered SEO metadata for public pages (BAN-262), read by
     * App\Support\Seo. Empty here because it is inherently per-client — a
     * client without its own block gets its app name and no description, which
     * is what every client had before this existed.
     *
     *   title       — <title> and og:title for crawlers that do not run JS
     *   description — meta description / og:description (aim 120–160 chars)
     *   site_name   — og:site_name and the title suffix used by app.jsx
     *   og_image    — absolute URL or a path relative to the public root
     *   twitter     — @handle, omitted when the client has none
     */
    'seo' => [],

    /*
     * How far outside a rental window a violation may still be attributed to
     * that rental, in hours. Covers late returns and same-day turnovers, which
     * the booking data cannot express (there is no actual-return timestamp).
     * Read via config('client.violation_match_grace_hours', 12).
     */
    'violation_match_grace_hours' => 12,

    /*
     * Legal ceiling (MAD) for a single cash payment/receipt. Above it, cash is
     * either rejected (cash_split off) or split into receipts each within this
     * cap (cash_split on). Read via config('client.cash_payment_max', 5000).
     */
    'cash_payment_max' => 5000,

    /*
     * Interface → concrete bindings resolved by ClientServiceProvider.
     * Core code injects the interface; each client supplies the class.
     */
    'bindings' => [],

    /*
     * branding_seed is not defined here because it is always client-specific.
     * Every client config file (config/clients/<client>.php) must define it.
     */

    /*
     * Client-specific copy for rental agreement terms.
     * Each client must define terms.rental_agreement. Empty string = no default.
     */
    'terms' => [
        'rental_agreement' => '',
    ],
];
