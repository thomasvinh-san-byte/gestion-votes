FROM php:8.3-fpm-alpine

LABEL org.opencontainers.image.title="AG-VOTE" \
      org.opencontainers.image.description="Application de gestion de votes en assemblée générale" \
      org.opencontainers.image.source="https://github.com/thomasvinh-san-byte/gestion-votes" \
      org.opencontainers.image.licenses="MIT"

# System dependencies
RUN apk add --no-cache \
    nginx supervisor postgresql-dev libpng-dev libjpeg-turbo-dev \
    freetype-dev libzip-dev icu-dev oniguruma-dev curl postgresql-client

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql gd zip intl mbstring opcache

# Redis extension (phpredis)
RUN apk add --no-cache --virtual .redis-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .redis-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Dependencies (cached layer)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress 2>/dev/null || \
    composer install --no-dev --no-interaction --no-progress

# Application
COPY . .

# Config files
COPY deploy/nginx.conf /etc/nginx/http.d/default.conf
COPY deploy/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf
COPY deploy/supervisord.conf /etc/supervisord.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# Permissions & directories
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/public \
    && mkdir -p /tmp/ag-vote /var/log/nginx /var/run/nginx \
    && chown -R www-data:www-data /tmp/ag-vote \
    && chmod +x /var/www/deploy/entrypoint.sh

# HTTP only — WebSocket proxied via nginx /ws path (port 8081 internal only)
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -f http://127.0.0.1:8080/ || exit 1

ENTRYPOINT ["/var/www/deploy/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
