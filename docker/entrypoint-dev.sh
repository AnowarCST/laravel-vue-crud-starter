#!/bin/sh
# Development entrypoint used by docker-compose.yml.
#
# On the first start of a fresh clone the bind-mounted project has no vendor/
# directory yet, so the app would fail every request (and every health check)
# until someone runs `composer install`. Install the PHP dependencies
# automatically in that case, then hand over to the CMD (apache2-foreground).
set -eu

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/ not found: running composer install (first start only)..."
    composer install --no-interaction --no-progress --prefer-dist
fi

exec "$@"
