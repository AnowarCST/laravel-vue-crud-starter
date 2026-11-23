<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="application-name" content="{{ config('app.name', 'Laravel Vue CRUD') }}">
  <meta name="color-scheme" content="light dark">
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
  <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
  <title>{{ config('app.name', 'Laravel') }}</title>
  @vite('resources/js/app.js')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
  {{-- The SPA mounts here. The current user is passed as Blade-escaped JSON in a
       data attribute (no inline script tag, CSP friendly) and parsed by resources/js/lib/user.js. --}}
  <div id="spa-app" data-user="{{ json_encode(auth()->user()->only(['id', 'name', 'email', 'type'])) }}"></div>

  {{-- Submitted by the SPA's logout buttons through a JS listener (no inline handlers). --}}
  <form id="logout-form" action="{{ route('logout') }}" method="POST" hidden>
    @csrf
  </form>

  <noscript>
    <div class="alert alert-warning m-3">This application requires JavaScript.</div>
  </noscript>
</body>
</html>
