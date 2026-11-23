#!/bin/sh
# Production entrypoint: warm Laravel caches from the runtime environment,
# then hand over to the CMD (apache2-foreground).
#
# Set LARAVEL_OPTIMIZE=false to skip (e.g. for one-off artisan containers).
# Migrations are NOT run automatically: take a database backup, then run
#   docker compose exec app php artisan migrate --force
set -eu

if [ "${LARAVEL_OPTIMIZE:-true}" = "true" ] && [ "${1:-}" = "apache2-foreground" ]; then
    php artisan optimize --no-interaction
fi

exec "$@"
