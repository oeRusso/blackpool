#!/bin/sh
# Prepares the Laravel app before handing over to php-fpm, the queue worker or the scheduler.
set -e

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true

    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        php artisan migrate --force --no-interaction
    fi
fi

php artisan optimize >/dev/null

exec "$@"
