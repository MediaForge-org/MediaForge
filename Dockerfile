# syntax=docker/dockerfile:1
# =============================================================================
# MediaForge — production image (multi-stage, minimal, non-root).
# One image runs every PHP role (app/worker/horizon/scheduler); the role is the
# container's command. Built for linux/amd64 + linux/arm64 (buildx-ready).
# =============================================================================

# --- Stage 1: PHP dependencies ----------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

# --- Stage 2: Frontend assets -----------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# --- Stage 3: Runtime -------------------------------------------------------
# Debian-based php-fpm (multi-arch, reliably available). Alpine is a future
# image-size optimization once its base layers are reachable again.
FROM php:8.4-fpm AS runtime

# PHP extensions via the extension installer (reliable across amd64/arm64).
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis intl zip bcmath pcntl opcache gd \
    && apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor postgresql-client tzdata \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/*

# Service configuration.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/mediaforge.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-mediaforge.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

# Application source, then optimized vendor + built assets from earlier stages.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Writable runtime directories (the only state the container writes locally;
# persistent data lives in mounted volumes).
RUN mkdir -p storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
        /tmp/nginx \
    && chown -R www-data:www-data storage bootstrap/cache /tmp/nginx \
    && chmod -R ug+rwx storage bootstrap/cache

USER www-data

# nginx listens on an unprivileged port so the container runs fully non-root.
EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
