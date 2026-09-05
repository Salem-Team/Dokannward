<?php

// Allows the decoupled Next.js Dokan Ward storefront (a different origin/port)
// to call this Laravel API and checkout endpoints directly from the browser.
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Production should set CORS_ALLOWED_ORIGINS (or FRONTEND_URL) explicitly.
    // Localhost origins are only appended outside production.
    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            env('FRONTEND_URL', 'http://localhost:3000')
                .(env('APP_ENV') === 'production' ? '' : ',http://localhost:3000,http://127.0.0.1:3000')
        ))
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
