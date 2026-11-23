<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API documentation (/docs)
    |--------------------------------------------------------------------------
    |
    | When enabled, /docs shows the OpenAPI document docs/api/openapi.yaml in
    | a self-hosted viewer, and /docs/openapi.yaml serves the document itself.
    |
    | Default: enabled only when APP_ENV=local. In the local environment
    | anyone who can reach the application may open the pages. In every other
    | environment they are only available after setting API_DOCS_ENABLED=true,
    | and then only to signed-in administrators with a verified email address
    | (see App\Http\Middleware\AuthorizeApiDocs). When disabled, both URLs
    | return 404.
    |
    */

    'enabled' => (bool) env('API_DOCS_ENABLED', env('APP_ENV', 'production') === 'local'),

];
