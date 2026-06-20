<?php

return [
    'paths'                => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'      => ['*'],
    'allowed_origins'      => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://localhost:3001',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers'      => ['*'],
    'exposed_headers'      => ['Authorization'],
    'max_age'              => 0,
    // Bearer-token auth does not require credentials (cookies) to be sent cross-origin.
    // Set true only if switching to cookie-based Sanctum SPA auth.
    'supports_credentials' => false,
];
