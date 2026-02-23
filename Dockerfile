FROM php:8.4-fpm-alpine3.21

LABEL org.opencontainers.image.title="AG-VOTE" \
      org.opencontainers.image.description="Application de gestion de votes en assemblée générale" \
      org.opencontainers.image.source="https://github.com/thomasvinh-san-byte/gestion-votes" \
      org.opencontainers.image.licenses="MIT"

# Runtime libs (permanent — explicitly installed so apk del won't touch them)
RUN apk add --no-cache \
    nginx supervisor curl postgresql-client libpq \
    libpng libjpeg-turbo freetype libzip icu-libs oniguruma \
    zlib zstd-libs brotli-libs lz4-libs

# Build-time headers in a virtual group (cleanly removed after compile)
RUN apk add --no-cache --virtual .php-build-deps \
    postgresql-dev libpng-dev libjpeg-turbo-dev \
    freetype-dev libzip-dev icu-dev oniguruma-dev

# PHP extensions (compile against -dev headers)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql gd zip intl mbstring opcache

# Redis extension (phpredis)
RUN apk add --no-cache --virtual .redis-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .redis-deps

# Remove build headers — only the .php-build-deps group; runtime libs stay.
# Then verify every required extension still loads (fail-fast guard).
RUN apk del .php-build-deps \
    && rm -rf /tmp/pear \
    && php -r 'foreach(["gd","intl","zip","pdo_pgsql","pgsql","mbstring","redis","opcache"] as $e){if(!extension_loaded($e)){fwrite(STDERR,"FATAL: ext-$e failed to load after cleanup\n");exit(1);}}'

# Composer (install deps then remove — not needed at runtime)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Dependencies (cached layer — lock file required)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && rm -f /usr/bin/composer

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

# HTTP only (real-time updates via HTTP polling, not WebSocket)
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -sf http://127.0.0.1:${PORT:-8080}/api/v1/health.php || exit 1

ENTRYPOINT ["/var/www/deploy/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
