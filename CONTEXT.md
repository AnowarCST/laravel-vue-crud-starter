# Project Context

## Overview
- Repository: `laravel-vue-crud-starter`. Default branch: `main`.
- Current work branch: `security/hardening-and-upgrade-2026` (security
  hardening + supported-version upgrade, see `SECURITY_REFACTOR_PLAN.md`).
- Features: session auth (laravel/ui controllers) + Passport API, CRUD for
  users/products/categories/tags, profile management, product image upload,
  Developer page for OAuth clients and personal access tokens.

## Current stack
- PHP 8.5, Laravel 13, Laravel Passport 13 (`auth:api`), laravel/ui auth
  controllers for the session login/register/reset flows.
- Vue 3 SPA + Vue Router, built with **Vite** (`vite.config.js`,
  `laravel-vite-plugin`, output in `public/build`). Laravel Mix and
  `webpack.mix.js` are gone; do not reintroduce `MIX_*` variables or
  `public/js`/`public/css` build artifacts.
- AdminLTE 4 + Bootstrap 5 + Font Awesome; SweetAlert2 for dialogs.
- MySQL 8.4 LTS (tests use in-memory SQLite, forced in `phpunit.xml`).
- Node 26 LTS for tooling only.

## Security conventions
- No seeded admin; admins are created with `php artisan app:create-admin`
  (interactive only). Registration always creates non-admin users.
- Password minimum 12 characters. Admin-only account management.
- Uploads: images only, random names, Laravel `public` disk
  (`storage/app/public/uploads`) served via `public/storage`; Apache never
  executes or sniffs files there.
- Security headers (nonce CSP, XFO, nosniff, Referrer-Policy,
  Permissions-Policy, COOP, HSTS on HTTPS) come from Laravel middleware;
  Apache only adds nosniff + ServerTokens/Signature/TRACE hardening.
- Named rate limiters for login, register, password reset, API.
- Health endpoint: Laravel built-in `/up` (used by Docker healthchecks).
- Policy and reporting: `SECURITY.md`.

## Docker / infrastructure
- `docker/Dockerfile` multi-stage: `base` (php:8.5.x-apache, extensions,
  Apache as www-data on 8080), `dev` (Composer, bind mount), `node-dev` (Vite
  dev server), `vendor`/`assets` (build-only), `prod` (immutable runtime,
  read-only code, writable `storage/` + `bootstrap/cache/` only,
  `php artisan optimize` in `docker/entrypoint-prod.sh`).
- `docker/apache/app.conf` (vhost, public/ only, upload lockdown) and
  `docker/apache/hardening.conf` (server-wide hardening).
- `docker-compose.yml`: `app` (dev target), `db` (mysql 8.4.x, not published),
  `node` (profile `dev`, Vite on 127.0.0.1:5173). All ports loopback-only;
  required secrets use `${VAR:?}`. `docker-compose.db-port.yml` opts in to a
  loopback MySQL port.
- Image tags are pinned to exact patch versions; Dependabot updates them.

## CI
- GitHub Actions: `.github/workflows/ci.yml` (php: validate/install/audit/test;
  frontend: npm ci/audit/build; docker: prod build + `/up` smoke test),
  `.github/workflows/codeql.yml` (javascript-typescript + actions).
  Third-party actions pinned to commit SHAs.
- `.github/dependabot.yml`: composer, npm, docker, docker-compose,
  github-actions; weekly, minor/patch grouped.
- `azure-pipelines.yml`: legacy Azure check kept alive (GitHub integration is
  external); builds the `dev` image and runs composer validate/audit + tests.

## Guiding principles
- Preserve CRUD parity and existing routes; document behavioural changes with
  migration + rollback steps (README "Upgrading from the Laravel 8 version").
- Never commit secrets; `.env.example` has empty placeholders only.
- Use Docker for PHP/Node commands; never reset or seed an existing database.
- Record real versions and test evidence; never claim unverified checks.

## Key references
- `SECURITY_REFACTOR_PLAN.md` - plan and acceptance checks for this refactor.
- `TASKS.md` - milestone status.
- `reports/deps.md`, `reports/routes.md` (local, gitignored) - pre-upgrade audit
  snapshots from the Laravel 8 era; historical, not current.
