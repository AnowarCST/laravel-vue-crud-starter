# syntax=docker/dockerfile:1
#
# Multi-stage image for the Laravel + Vue starter.
#
#   docker build -f docker/Dockerfile --target dev  -t laravel-vue-starter:dev  .
#   docker build -f docker/Dockerfile --target prod -t laravel-vue-starter:prod .
#
# Targets
#   base   PHP 8.5 + Apache with the extensions Laravel 13 / Passport 13 need.
#          Apache runs as the unprivileged www-data user and listens on 8080.
#   dev    base + Composer/git/unzip, used by docker-compose with a bind mount.
#   node-dev  Node 26 image running the Vite dev server (compose profile "dev").
#   prod   immutable image: no-dev vendor/, built Vite assets, read-only code,
#          writable storage/ and bootstrap/cache only, no Composer/Node/git.
#
# Image tags are pinned to exact patch releases; Dependabot (docker ecosystem)
# proposes updates.

ARG PHP_IMAGE=php:8.5.13-apache-trixie
ARG COMPOSER_IMAGE=composer:2.11.2
ARG NODE_IMAGE=node:26.13.1-trixie-slim

FROM ${COMPOSER_IMAGE} AS composer-bin

# ---------------------------------------------------------------------------
# base: runtime shared by dev and prod
# ---------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS base

WORKDIR /var/www/html

# Build the extensions, then drop the -dev packages and keep only the shared
# libraries the compiled extensions link against (same approach as the
# official php image). mbstring, pdo_sqlite, sodium, openssl and OPcache are
# already compiled into the base image.
RUN set -eux; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
        libzip-dev libicu-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd bcmath pcntl; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark > /dev/null; \
    find /usr/local -type f -executable -exec ldd '{}' ';' \
        | awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
        | sort -u | xargs -r dpkg-query --search | grep -v '^diversion by' \
        | cut -d: -f1 | sort -u | xargs -r apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/* /tmp/* /usr/src/php.tar.xz*; \
    for ext in bcmath gd intl mbstring openssl pcntl pdo_mysql pdo_sqlite sodium zip 'Zend OPcache'; do \
        php -m | grep -qixF "$ext" || { echo "missing PHP extension: $ext" >&2; exit 1; }; \
    done; \
    ! php -v 2>&1 | grep -qi 'warning'

# Apache: unprivileged port, hardened global config, app vhost only.
COPY docker/apache/hardening.conf /etc/apache2/conf-available/hardening.conf
COPY docker/apache/app.conf /etc/apache2/sites-available/000-default.conf
RUN set -eux; \
    sed -ri 's/^Listen 80$/Listen 8080/; /Listen 443/d' /etc/apache2/ports.conf; \
    a2enmod rewrite headers; \
    a2dismod -f autoindex status; \
    a2disconf security serve-cgi-bin; \
    a2enconf hardening; \
    rm -f /var/www/html/index.html

COPY docker/php/local.ini /usr/local/etc/php/conf.d/zz-app.ini

EXPOSE 8080

# ---------------------------------------------------------------------------
# dev: bind-mount friendly image used by docker-compose.yml
# ---------------------------------------------------------------------------
FROM base AS dev

# Match www-data to the host user so bind-mounted files stay writable on
# Linux hosts (Docker Desktop on macOS/Windows maps ownership itself).
ARG HOST_UID=1000
ARG HOST_GID=1000

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*; \
    groupmod -o -g "${HOST_GID}" www-data; \
    usermod -o -u "${HOST_UID}" -g "${HOST_GID}" www-data; \
    chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2 /var/www

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY docker/php/opcache-dev.ini /usr/local/etc/php/conf.d/zz-opcache.ini

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent --show-error --output /dev/null http://127.0.0.1:8080/up || exit 1

# ---------------------------------------------------------------------------
# node-dev: Vite dev server for docker-compose (profile "dev")
# ---------------------------------------------------------------------------
FROM ${NODE_IMAGE} AS node-dev

# Pre-create node_modules owned by the unprivileged "node" user so the named
# volume mounted there inherits that ownership (host node_modules, built for
# the host OS, is never used inside the container).
RUN mkdir -p /app/node_modules && chown -R node:node /app
WORKDIR /app
ENV npm_config_cache=/tmp/.npm npm_config_update_notifier=false
USER node
EXPOSE 5173
CMD ["sh", "-c", "npm ci --no-audit --no-fund && exec npm run dev -- --host 0.0.0.0"]

# ---------------------------------------------------------------------------
# vendor: production Composer install (build-only stage)
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_HOME=/tmp/composer COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader

COPY . .
RUN set -eux; \
    rm -rf tests public/hot public/storage .env; \
    composer dump-autoload --no-dev --optimize --no-interaction; \
    composer check-platform-reqs --no-dev

# ---------------------------------------------------------------------------
# assets: Vite production build (build-only stage)
# ---------------------------------------------------------------------------
FROM ${NODE_IMAGE} AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN npm run build && test -f public/build/manifest.json

# ---------------------------------------------------------------------------
# prod: final runtime image
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/entrypoint-prod.sh /usr/local/bin/entrypoint-prod

# Application code is owned by root and read-only for the web server.
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN set -eux; \
    mkdir -p storage/app/public/uploads storage/app/private \
        storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache; \
    rm -rf public/storage public/upload; \
    ln -s ../storage/app/public public/storage; \
    chown -R root:root /var/www/html; \
    chmod -R a+rX,go-w /var/www/html; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R u+rwX,g+rX,o-rwx storage bootstrap/cache; \
    chmod 0755 /usr/local/bin/entrypoint-prod

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl --fail --silent --show-error --output /dev/null http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["entrypoint-prod"]
CMD ["apache2-foreground"]
