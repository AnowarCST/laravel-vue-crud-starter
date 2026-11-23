<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The bundled SPA is served from the same origin as the API, so browsers do
    | not need CORS for it. Cross-origin access is limited to the application's
    | own URL plus any origins listed (comma separated) in CORS_ALLOWED_ORIGINS.
    | Never combine a wildcard origin with credentials.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        fn (string $origin) => rtrim(trim($origin), '/'),
        [(string) env('APP_URL', ''), ...explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))],
    ), fn (string $origin) => $origin !== '' && $origin !== '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
