#!/usr/bin/env bash
# MediaForge container entrypoint. Prepares writable dirs, then execs the role
# command (supervisor for `app`, or `php artisan ...` for worker roles).
set -euo pipefail

cd /var/www/html

# Ensure the writable runtime dirs exist (bind-mounted dev volumes start empty).
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    /tmp/nginx/client /tmp/nginx/proxy /tmp/nginx/fastcgi /tmp/nginx/uwsgi /tmp/nginx/scgi

# In production, cache config/routes/views for speed (idempotent, safe to rerun).
if [ "${APP_ENV:-production}" = "production" ] && [ "${MEDIAFORGE_SKIP_OPTIMIZE:-0}" != "1" ]; then
    php artisan config:cache --no-interaction || true
    php artisan route:cache --no-interaction || true
    php artisan view:cache --no-interaction || true
fi

exec "$@"
