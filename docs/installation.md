# Installation and deployment

The short version is in the [README](../README.md#quick-start-with-docker).
This page has the details: the Docker development stack, installing without
Docker, and the production image.

## Docker development stack

Requirements: Docker Engine 24+ with Docker Compose v2.

`docker-compose.yml` starts three services: `app` (Apache and PHP, with the
project bind mounted), `db` (MySQL 9.7) and `mailpit`. The `node` service
(Compose profile `dev`) runs the Vite dev server. See
[ARCHITECTURE.md](../ARCHITECTURE.md#docker-topology) for the details.

- Set **strong, different** values for `DB_PASSWORD` and `DB_ROOT_PASSWORD`
  in `.env`, for example with `openssl rand -base64 32`. Compose refuses to
  start while they are empty. On Linux, also set `HOST_UID`/`HOST_GID` to the
  output of `id -u`/`id -g`.
- On the first start of a fresh clone, the `app` container runs
  `composer install` before Apache starts. Wait until Apache answers before
  running Artisan commands, as the README's quick start does. Until
  `APP_KEY` is set, `/up` answers with an error and the container's health
  check fails; that is expected. `docker compose exec app composer install`
  is safe to run later, but not while the first install is still running:
  two installs at once fail.
- Every email the app sends (account verification, password reset) is caught
  by Mailpit. Read it at <http://localhost:8025> (`MAILPIT_PORT`).
- Self-registered accounts must verify their email address before they can
  use the dashboard. Accounts created with `app:create-admin` or by an
  administrator are verified automatically.
- The app is on <http://localhost:8000>; change `APP_PORT` in `.env` to use
  another port. To use another port for the Vite dev server, set `VITE_PORT`
  in `.env` before starting the `node` service.
- All ports bind to `127.0.0.1` only. MySQL is not published. To reach it
  from a GUI client, start the stack with
  `docker compose -f docker-compose.yml -f docker-compose.db-port.yml up -d`
  and connect to `127.0.0.1:${DB_FORWARD_PORT:-3307}`.
- The `node` container keeps its Linux `node_modules` in a Docker volume. Your
  host's `node_modules` is never used inside the container.
- Health check: `curl http://localhost:8000/up`.

### Front-end assets

```bash
docker compose --profile dev up -d node   # Vite dev server with hot reload on http://localhost:5173
# or build once without the dev server:
docker compose --profile dev run --rm node sh -c "npm ci && npm run build"
```

### Demo data

Optional: an everyday retail catalogue with 6 categories, 8 tags and 14
products, each with an original illustration. It creates no user accounts,
and running it again does not add duplicates or change existing rows:

```bash
docker compose exec app php artisan db:seed
```

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
assets. It does not include Composer, Node or git. `.dockerignore` also keeps
the tests, the documentation (except `docs/api/openapi.yaml`, which `/docs`
serves), the Markdown files (except `THIRD_PARTY_NOTICES.md`), the AI
assistant instructions (`AGENTS.md`, `CLAUDE.md`, `.claude/`, `.cursor/`) and
the development tool configuration out of the image. Apache runs as `www-data` on
port 8080, and the image has a `HEALTHCHECK` on `/up`. Provide configuration as
environment variables (never bake `.env` into the image). Mount `storage/` on a
persistent volume, and run migrations explicitly after taking a backup:

```bash
docker exec <container> php artisan migrate --force
```

See the production checklist in
[SECURITY.md](../SECURITY.md#production-checklist), and the deployment
templates for GitHub Actions, AWS and Azure in `deploy/`
([docs/ci-cd.md](ci-cd.md#deployment-templates)).
