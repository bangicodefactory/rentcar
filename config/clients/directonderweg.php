<?php

return [

    'name'               => 'Direct Onderweg',
    'default_locale'     => 'nl',
    'supported_locales'  => ['nl', 'fr', 'en', 'ar'],

    'features' => [
        'paypal'          => false,
        'stripe'          => false,
        'subscriptions'   => false,
        'booking_payment' => false,
        'excel_import'    => true,
        'multi_branch'    => false,
        'tva_renumber'    => true,
        'signatures'      => true,
        // Cash over `cash_payment_max` (5000 MAD, CGI art. 193) is split into
        // compliant receipts instead of being refused at the counter. Each
        // receipt gets its own facture, on distinct days, with the rental days
        // apportioned. Turned on for this client 2026-08-10.
        'cash_split'      => true,
        'invoice_on_full_payment' => false,  // keep today's behavior: one invoice per payment
        // Off 2026-08-10. The module and its table stay in place — only the
        // routes and the sidebar entry disappear, so turning it back on is a
        // config flip with no data migration. Any rows already recorded are
        // retained, just unreachable while this is false.
        'traffic_violations' => false,
        'public_storefront' => true,  // BAN-261: unchanged — keeps today's behavior
    ],

    /*
     * A client may return the car up to 7 hours late (7h00 included) without
     * being charged an extra day; 7h01 adds one. Default is 14 min.
     */
    'late_return_grace_minutes' => 420,

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

    /*
     * Client-specific rental-agreement terms. Replaces config/default_terms.php
     * (BAN-179). Read via config('client.terms.rental_agreement').
     */
    'terms' => [
        'rental_agreement' => <<<'EOD'
Entreprise : Directonderweg\n
Adresse: Rzini 3, RDC EL HANI BOUJARRAH SOUFLA, Tétouan 93030\n
ICE : 002895399000019\n
Article 1 : Objet du contrat\n
Ces conditions générales ont pour objet de définir les droits et obligations de l'entreprise Directonderweg (ci-après "le Loueur") et de toute personne louant un véhicule (ci-après "le Locataire") dans le cadre de la location de voitures.\n
Article 2 : Conditions d'accès à la location\n
Le Locataire doit être âgé d'au moins 21 ans et titulaire d'un permis de conduire valide depuis au moins 2 ans.\n
Une pièce d'identité (passeport ou carte nationale) et une copie du permis de conduire doivent être présentées lors de la signature du contrat.\n
Article 3 : Durée de la location\n
La durée de la location est déterminée dans le contrat.\n
Toute prolongation doit être demandée et validée par le Loueur avant la fin du contrat initial.\n
Article 4 : Prix et paiement\n
Le prix de la location est convenu entre le Loueur et le Locataire avant la signature du contrat.\n
Le paiement doit être effectué avant la remise du véhicule, sauf accord contraire.\n
Une caution peut être exigée et sera restituée au retour du véhicule, sous réserve de l'état du véhicule.\n
Article 5 : Utilisation du véhicule\n
Le Locataire s'engage à utiliser le véhicule de manière responsable et conformément à la réglementation en vigueur.\n
Le véhicule ne doit pas être sous-loué ou utilisé à des fins illégales.\n
Le Locataire doit informer immédiatement le Loueur en cas d'accident, de vol ou de panne.\n
Article 6 : Assurance\n
Les véhicules loués sont couverts par une assurance tous risques avec franchise.\n
En cas de dommage ou d'accident, le Locataire est responsable de la franchise indiquée dans le contrat.\n
La perte ou les dégradations des accessoires (clés, pneus, etc.) sont à la charge du Locataire.\n
Article 7 : Restitution du véhicule\n
Le véhicule doit être restitué à la date, à l'heure et au lieu prévus dans le contrat.\n
Toute restitution tardive peut entraîner des frais supplémentaires.\n
Le véhicule doit être rendu dans le même état qu'au moment de la remise, avec le même niveau de carburant.\n
Article 8 : Responsabilité\n
Le Locataire est entièrement responsable des infractions commises pendant la période de location.\n
Le Loueur ne peut être tenu responsable des objets oubliés dans le véhicule.\n
Article 9 : Résiliation du contrat\n
En cas de non-respect des termes du contrat, le Loueur se réserve le droit de le résilier sans préavis.\n
Toute annulation par le Locataire doit être notifiée au moins 48 heures à l'avance.\n
Article 10 : Litiges\n
En cas de litige, les parties s'efforceront de trouver une solution amiable. À défaut, les tribunaux de Tétouan seront seuls compétents.\n
Fait à Tétouan.\n
EOD,
    ],
];
