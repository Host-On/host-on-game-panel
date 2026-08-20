<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Host-On Games Branding
    |--------------------------------------------------------------------------
    |
    | Centralized branding configuration. Avoid hardcoding the product name in
    | individual views or components; use these values instead.
    |
    */

    'brand' => [
        'name' => env('APP_NAME', 'Host-On.Games'),
        'product' => env('HOSTON_PRODUCT_NAME', 'Host-On.Games Panel'),
        'company' => env('HOSTON_COMPANY', 'Host-On'),
        'domain' => env('HOSTON_DOMAIN', 'host-on.games'),
        'support_email' => env('HOSTON_SUPPORT_EMAIL', 'support@host-on.games'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo infrastructure provider
    |--------------------------------------------------------------------------
    |
    | When enabled, the Demo/Fake infrastructure provider is available so the
    | full provisioning flow can be demonstrated without a real Proxmox
    | cluster. This must be explicitly disabled in production.
    |
    */

    'demo' => [
        'enabled' => env('HOSTON_DEMO_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provisioning
    |--------------------------------------------------------------------------
    |
    | Tuning for the provisioning state machine.
    |
    */

    'provisioning' => [
        'queue' => env('HOSTON_PROVISIONING_QUEUE', 'default'),
        'bootstrap_token_ttl_minutes' => (int) env('HOSTON_BOOTSTRAP_TOKEN_TTL', 60),
    ],
];
