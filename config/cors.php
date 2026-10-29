<?php

return [
    // Sanctum's CSRF bootstrap is outside /api, so both paths must be covered.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Comma-separated full origins (scheme + host + optional port), without a
    // trailing slash. Never use '*' when allowing credentialed requests.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://masria.elmotechsoft.online')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Required for the React SPA's Sanctum session cookies and CSRF requests.
    'supports_credentials' => true,
];
