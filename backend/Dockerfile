# Laravel API served by FrankenPHP (Caddy + PHP in one process) on port 80.
FROM dunglas/frankenphp:1-php8.3-alpine

RUN install-php-extensions pdo_pgsql pgsql intl zip opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first, so code changes don't reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION} \
    SERVER_NAME=":80" \
    PHP_INI_SCAN_DIR=":/usr/local/etc/php/app.conf.d"

COPY docker/php.ini /usr/local/etc/php/app.conf.d/app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
