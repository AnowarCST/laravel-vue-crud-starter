<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Read by resources/js/api-docs.js: "Try it out" requests reuse the
         signed-in session (laravel_token cookie), which needs this token. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>API documentation · {{ config('app.name', 'Laravel') }}</title>
    @vite('resources/js/api-docs.js')
</head>
<body>
    {{-- The viewer mounts here. The document URL is passed in a data
         attribute; the page has no inline scripts (CSP). --}}
    <div id="api-docs" data-spec-url="{{ $specUrl }}"></div>

    <noscript>
        <p>The interactive viewer needs JavaScript. The raw OpenAPI document is at
            <a href="{{ $specUrl }}">{{ $specUrl }}</a>.</p>
    </noscript>
</body>
</html>
