# Project context

A short orientation for contributors. For setup instructions see
[README.md](README.md); for security rules see [SECURITY.md](SECURITY.md).

## Overview

- Repository: `laravel-vue-crud-starter`. Default branch: `main`.
- Features: session login (laravel/ui controllers) with email verification,
  an OAuth2 API (Laravel Passport), CRUD screens for users, products,
  categories and tags, profile management, product image upload, and a
  Developer page for OAuth clients and personal access tokens.

## Stack

- PHP 8.5, Laravel 13, Laravel Passport 13 (`auth:api` guard), laravel/ui
  authentication controllers.
- Vue 3 single-page application with Vue Router, built with Vite
  (`vite.config.js`, output in `public/build`). Entry points:
  `resources/js/app.js` (the dashboard) and `resources/js/auth.js` (login,
  registration and other guest pages).
- AdminLTE 4, Bootstrap 5, Font Awesome and SweetAlert2. Fonts are bundled;
  nothing is loaded from a CDN.
- MySQL 9.7 LTS. Tests use an in-memory SQLite database (forced in
  `phpunit.xml`).
- Node 26 LTS, used for build tooling only.

## How the dashboard talks to the API

- The dashboard is a Blade page (`resources/views/home.blade.php`) that loads
  the Vue app. The signed-in user is passed to Vue through the `data-user`
  attribute on `#spa-app`. There are no inline scripts.
- API calls go to `/api/*` with Passport's `laravel_token` cookie plus the
  CSRF header. Every authenticated API route also requires a verified email
  address.

## Security conventions

- No seeded admin account. Admins are created with
  `php artisan app:create-admin` (interactive only). Registration always
  creates regular users.
- Passwords are at least 12 characters. Account management is admin only.
- Uploads: images only, re-encoded on the server, stored under random names on
  the `public` disk (`storage/app/public/uploads`) and served through
  `public/storage`. Apache never executes files there.
- Security headers (nonce-based CSP, X-Frame-Options, nosniff,
  Referrer-Policy, Permissions-Policy, COOP, and HSTS on HTTPS) come from
  `app/Http/Middleware/SecurityHeaders.php`.
- Named rate limiters for login, registration, password reset and the API are
  defined in `app/Providers/AppServiceProvider.php`.
- Health endpoint: Laravel's built-in `/up`.

## Docker

- `docker/Dockerfile` targets: `dev` (used by `docker-compose.yml`, installs
  Composer dependencies on first start), `node-dev` (Vite dev server) and
  `prod` (read-only application code, no build tools).
- `docker-compose.yml` services: `app`, `db` (MySQL, not published),
  `mailpit` (email inbox on port 8025) and `node` (profile `dev`). All ports
  bind to `127.0.0.1`.

## Quality checks

- PHP: `composer lint` (Pint), `composer analyse` (Larastan level 9),
  `php artisan test`, `composer audit`.
- JavaScript and Vue: `npm run lint` (ESLint), `npm run format:check`
  (Prettier), `npm run build`, `npm audit`.
- CI runs all of the above (`.github/workflows/ci.yml`), plus CodeQL and a
  production Docker build.

## Guiding principles

- Keep existing routes and CRUD behaviour working. Document behaviour changes
  with upgrade and rollback steps in the README.
- Never commit secrets. `.env.example` contains empty placeholders only.
- Record real versions and real test results. Do not claim a check passed
  unless it was run.
