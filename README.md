# Laravel + Vue CRUD Starter

[![CI](https://github.com/AnowarCST/laravel-vue-crud-starter/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/AnowarCST/laravel-vue-crud-starter/actions/workflows/ci.yml)
[![CodeQL](https://github.com/AnowarCST/laravel-vue-crud-starter/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/AnowarCST/laravel-vue-crud-starter/actions/workflows/codeql.yml)

## About

A simple Laravel + Vue 3 + AdminLTE single-page application (SPA) starter
with CRUD screens, authentication and an OAuth2 API.

## Tech stack

- PHP 8.5, Laravel 13, Laravel Passport 13 (OAuth2 / personal access tokens)
- Vue 3 + Vue Router, built with Vite (SweetAlert2, axios)
- AdminLTE 4 + Bootstrap 5 + Font Awesome
- MySQL 9.7 LTS
- Node 26 LTS (build tooling only)
- PHPUnit tests, including security regression tests
- Pint and Larastan (PHP), ESLint and Prettier (JavaScript and Vue)
- Docker (Apache + mod_php) for development and production, Mailpit for
  local email

## Features

- Modal-based create and edit, paginated lists, delete with confirmation
- Login, register, email verification, forgot and reset password
- Profile: update name and email, change password
- Product catalogue with SKU, stock level and status (active, draft,
  archived), search, filters and sorting that are kept in the URL, and image
  upload
- Dashboard with product counts and a low-stock list
- User management (admin only)
- Categories and tags (admin only in the menu)
- Role-based access control (admin and user) on the API and in the UI
- Developer page for OAuth clients and personal access tokens
- Hardened by default, see [SECURITY.md](SECURITY.md)

## Quick start with Docker (recommended)

Requirements: Docker Engine 24+ with Docker Compose v2.

```bash
git clone https://github.com/AnowarCST/laravel-vue-crud-starter.git
cd laravel-vue-crud-starter
cp .env.example .env
```

Edit `.env` and set **strong, different** values for `DB_PASSWORD` and
`DB_ROOT_PASSWORD`. You can generate them with `openssl rand -base64 32`.
Compose refuses to start while they are empty. On Linux, also set
`HOST_UID`/`HOST_GID` to the output of `id -u`/`id -g`.

```bash
docker compose up -d --build                          # app, MySQL and Mailpit
docker compose exec app composer install              # already run on first start; safe to repeat
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan passport:keys
docker compose exec app php artisan passport:client --personal --no-interaction   # for the Developer > Personal Access Tokens page
docker compose exec app php artisan storage:link
docker compose exec app php artisan app:create-admin  # prompts for email, name and password
```

Front-end assets:

```bash
docker compose --profile dev up -d node   # Vite dev server with hot reload on http://localhost:5173
# or build once without the dev server:
docker compose --profile dev run --rm node sh -c "npm ci && npm run build"
```

Open <http://localhost:8000> (change `APP_PORT` in `.env` to use another port).

Optional demo data: an everyday retail catalogue with 6 categories, 8 tags
and 14 products, each with an original illustration. It creates no user
accounts, and running it again does not add duplicates or change existing
rows:

```bash
docker compose exec app php artisan db:seed
```

Notes:

- Every email the app sends (account verification, password reset) is caught
  by Mailpit. Read it at <http://localhost:8025> (`MAILPIT_PORT`).
- Self-registered accounts must verify their email address before they can use
  the dashboard. Accounts created with `app:create-admin` or by an
  administrator are verified automatically.
- To use another port for the Vite dev server, set `VITE_PORT` in `.env`
  before starting the `node` service.
- All ports bind to `127.0.0.1` only. MySQL is not published. To reach it
  from a GUI client, start the stack with
  `docker compose -f docker-compose.yml -f docker-compose.db-port.yml up -d`
  and connect to `127.0.0.1:${DB_FORWARD_PORT:-3307}`.
- The `node` container keeps its Linux `node_modules` in a Docker volume. Your
  host's `node_modules` is never used inside the container.
- Health check: `curl http://localhost:8000/up`.

## Installing without Docker

Requirements:

- PHP 8.5 with `gd` (built with JPEG, PNG and WebP support), `pdo_mysql`,
  `sodium`, `fileinfo`, `mbstring`, `openssl`, `dom` and `tokenizer`
  (`composer check-platform-reqs` lists anything missing)
- Composer 2
- Node 26 LTS and npm
- MySQL 9.7 LTS (8.4 LTS also works)
- A way to receive email: run Mailpit locally, or set `MAIL_MAILER=log` to
  write emails (including verification links) to `storage/logs/laravel.log`

```bash
cp .env.example .env          # set DB_HOST=127.0.0.1, your DB credentials and MAIL_* settings
composer install
php artisan key:generate
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal --no-interaction
php artisan storage:link
php artisan app:create-admin
npm ci
npm run dev                   # or: npm run build
php artisan serve
```

Point a production web server at `public/` only.

## Production image

```bash
docker build -f docker/Dockerfile --target prod -t laravel-vue-crud-starter:prod .
```

The image includes production Composer dependencies and the built Vite
assets. It does not include Composer, Node or git. Apache runs as `www-data` on
port 8080, and the image has a `HEALTHCHECK` on `/up`. Provide configuration as
environment variables (never bake `.env` into the image). Mount `storage/` on a
persistent volume, and run migrations explicitly after taking a backup:

```bash
docker exec <container> php artisan migrate --force
```

See the production checklist in [SECURITY.md](SECURITY.md#production-checklist).

## Tests and code quality

```bash
docker compose exec app php artisan test
# or a single suite
docker compose exec app php artisan test --testsuite=Feature
```

Tests always use an in-memory SQLite database and never touch your MySQL data.

| Check | Command |
| --- | --- |
| PHP code style (Pint) | `docker compose exec app composer lint` (fix: `composer format`) |
| PHP static analysis (Larastan, level 9) | `docker compose exec app composer analyse` |
| JavaScript and Vue lint (ESLint) | `docker compose --profile dev run --rm node npm run lint` (fix: `npm run lint:fix`) |
| JavaScript and Vue formatting (Prettier) | `docker compose --profile dev run --rm node npm run format:check` (fix: `npm run format`) |
| Dependency audits | `docker compose exec app composer audit` and `docker compose --profile dev run --rm node npm audit` |

Without Docker, run the same `composer` and `npm` commands directly. CI runs
all of these, plus a production Vite build and a production Docker build (see
`.github/workflows/ci.yml`).

## Upgrading from the Laravel 8 version

This release has breaking changes. Read all of the steps before upgrading an
existing installation.

1. **Back up the database** (for example
   `mysqldump --single-transaction --routines -u<user> -p <database> > backup.sql`)
   and keep a copy of `storage/`, `public/upload/` and your current image or
   commit.
2. **Update `.env`** against the new `.env.example`. `DB_USERNAME` must not be
   `root`. Set `DB_ROOT_PASSWORD`. `MIX_*` and `PUSHER_*` variables are gone.
   Laravel 11+ names include `CACHE_STORE` (was `CACHE_DRIVER`) and
   `BROADCAST_CONNECTION`. `APP_DEBUG` now defaults to `false`.
3. **Runtime**: PHP 8.5, MySQL 9.7 LTS (or 8.4 LTS) and Node 26 are required. The Docker stack
   now uses a single Apache + PHP container on internal port 8080 (published as
   `APP_PORT`, default 8000) instead of nginx + php-fpm. Adminer and the public
   MySQL port were removed.
4. **Laravel Mix has been replaced by Vite.** `webpack.mix.js`,
   `public/js/app.js`, `public/css/app.css` and `mix-manifest.json` are no
   longer used. Run `npm ci && npm run build` (assets go to `public/build`).
5. **Registration no longer creates admins.** Every self-registered account is
   a regular user. The seeded `admin@gmail.com` / `123456` account has been
   removed from the seeders. Create administrators with
   `php artisan app:create-admin`. If an old database still contains that
   account, delete it or change its password immediately.
6. **Passwords** must now be at least 12 characters. Existing passwords keep
   working until they are changed.
7. **Uploads** moved from `public/upload` to the Laravel `public` disk
   (`storage/app/public/uploads`), served via `public/storage`. Run
   `php artisan storage:link`. Copy any existing images you want to keep into
   `storage/app/public/uploads/`. Only jpg, png and webp images are accepted.
8. **Passport 13**: run `php artisan migrate` to apply the new OAuth and
   session tables, `php artisan passport:keys` if the keys are missing, and
   `php artisan passport:client --personal` for personal access tokens.
   Existing tokens may need to be re-issued. Databases created by Passport 10
   use integer client IDs: set `PASSPORT_CLIENT_UUIDS=false`, and run
   `php artisan passport:hash` once to hash existing client secrets.
9. **Email verification is now required** for self-registered accounts, so
   configure `MAIL_*` with a working mail server. The migration
   `2026_11_23_000002_mark_existing_users_as_verified` marks all existing
   accounts as verified, so nobody is locked out by the upgrade.
10. **Uploaded images are re-encoded** on the server. This removes metadata
    such as EXIF and GPS data and anything hidden inside the file. Images
    that cannot be decoded are rejected.
11. **Products have new columns and a numeric price.** The migration
    `2026_11_23_000003_add_inventory_fields_to_products_table` adds `sku`
    (unique, empty for existing products), `stock` (0) and `status`
    (`active`), and stores `price` as `decimal(10,2)`. Before converting, it
    rewrites existing prices: values that are not numbers or are negative
    become 0.00, and values above 99999999.99 are capped. A SKU is required
    when creating a product. Rolling back removes the SKU, stock and status
    values. Running `php artisan db:seed` adds the new demo catalogue but does
    not remove the old car demo products, categories or tags, and the old car
    photos are no longer shipped. Delete those rows in the UI if you do not
    want them.
12. **Migrate** with `php artisan migrate --force`, then
    `php artisan optimize:clear`.

**Rollback**: redeploy the previous image or commit, then restore the database
backup taken in step 1 (`mysql ... < backup.sql`) and the saved `storage/`.
Migrations are not guaranteed to be reversible, so restore the backup instead
of relying on `migrate:rollback`.

**Existing Docker data**: the old stack (MySQL 8.0.17, root password
`123456`, `mysql_native_password`) stored data in the `dbdata` volume. The new
stack uses a fresh `db_data` volume with a non-root application user, so the
old data is not picked up automatically. Before switching, dump it from the old
stack (`mysqldump` as in step 1, run on the old checkout), then start the new
stack and import it:
`docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "$MYSQL_DATABASE"' < backup.sql`.
Remove the old volume and its weak root password once you have verified the
upgrade.

**Docker stack already on MySQL 8.4**: the compose file now uses MySQL 9.7
LTS. MySQL upgrades an 8.4 data directory to 9.7 automatically the first time
the new container starts (an upgrade between two LTS releases is supported).
Take a dump first, as in step 1, because the upgraded data directory cannot be
opened by MySQL 8.4 again. To roll back, start a fresh `mysql:8.4` volume and
import the dump.

## Security

Please report vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## Images

The logo (`public/images/logo.svg` and the favicons) and the product
illustrations (`public/images/products`) were made for this project and are
released under the MIT license with the rest of the code. Their sources are in
`resources/images`; see [resources/images/README.md](resources/images/README.md).

## License

[MIT license](https://opensource.org/licenses/MIT).
