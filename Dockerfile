# syntax=docker/dockerfile:1
# Production image (ADR-0003): FrankenPHP (Caddy) without Octane. The same
# image runs the app, Horizon and the scheduler; only the command differs.

ARG PHP_IMAGE=dunglas/frankenphp:1-php8.5-bookworm

FROM ${PHP_IMAGE} AS base

RUN install-php-extensions pdo_pgsql redis pcntl zip intl bcmath opcache exif \
    && apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg unzip \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini

WORKDIR /app

# ---------------------------------------------------------------------------
# Build: Composer + bun. Wayfinder's Vite plugin runs artisan, so the frontend
# is built in a PHP image with vendor/ present.
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=oven/bun:1 /usr/local/bin/bun /usr/local/bin/bun

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY package.json bun.lock ./
RUN bun install --frozen-lockfile

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && bun run build \
    && rm -rf node_modules resources/js/actions resources/js/routes resources/js/wayfinder \
    && rm -f bootstrap/cache/*.php

# ---------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production \
    SERVER_NAME=:8080 \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data

COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
COPY --from=build --chown=www-data:www-data /app /app

# Storage symlinks (config/filesystems.php "links"); storage/app is a bind
# mount in production, so the links are baked in and point into it.
RUN ln -sfn /app/storage/app/public /app/public/storage \
    && ln -sfn /app/storage/app/hls /app/public/hls \
    && chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p /config/caddy /data/caddy \
    && chown -R www-data:www-data /config /data /app/storage /app/bootstrap/cache

USER www-data

EXPOSE 8080

# The base image probes Caddy's admin API, which is off here. The app
# service gets its own HTTP check in deploy/compose.production.yml.
HEALTHCHECK NONE

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
