#!/bin/sh
set -e

# Cache config/routes for production, then apply pending migrations.
# Only the api container migrates (RUN_MIGRATIONS=true), so the queue worker doesn't race it.
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
