#!/bin/sh
set -e

cd /app

# storage/app is a bind mount; make sure the expected tree exists.
mkdir -p storage/app/private storage/app/public storage/app/hls \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs

if [ "${APP_ENV}" = "production" ] && [ "${APP_SKIP_OPTIMIZE:-false}" != "true" ]; then
    php artisan optimize --no-interaction
fi

exec "$@"
