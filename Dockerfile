# ============================================================
# RUMS — Production Dockerfile
# Multi-stage: builder (deps) → runtime (lean image)
# ============================================================

# ── Stage 1: Build / dependency stage ────────────────────────
FROM php:8.3-fpm-alpine AS builder

# System dependencies for PHP extensions
RUN apk add --no-cache \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        libzip-dev oniguruma-dev icu-dev \
        $PHPIZE_DEPS

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j"$(nproc)" \
        pdo pdo_mysql mysqli \
        gd zip mbstring intl \
        opcache bcmath

# Tune OPcache for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# ── Stage 2: Runtime image ────────────────────────────────────
FROM php:8.3-fpm-alpine AS runtime

# Runtime system deps only
RUN apk add --no-cache \
        nginx supervisor curl \
        libpng libjpeg-turbo freetype \
        libzip icu-libs \
        tzdata

# Copy compiled PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/     /usr/local/etc/php/conf.d/

# PHP runtime configuration
RUN { \
    echo 'expose_php=Off'; \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=12M'; \
    echo 'max_execution_time=60'; \
    echo 'memory_limit=256M'; \
    echo 'date.timezone=${APP_TIMEZONE:-Africa/Nairobi}'; \
    echo 'session.cookie_secure=1'; \
    echo 'session.cookie_httponly=1'; \
    echo 'session.use_strict_mode=1'; \
} > /usr/local/etc/php/conf.d/rums.ini

# Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Supervisor config (manages php-fpm + nginx)
COPY docker/supervisord.conf /etc/supervisord.conf

# Application code
WORKDIR /var/www/html

COPY . .

# Create required directories, set permissions
RUN mkdir -p storage/logs storage/cache assets/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 storage assets/uploads

# Remove sensitive files from image
RUN rm -f .env docker-compose.yml docker-compose.override.yml

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://localhost/api/v1/health || exit 1

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
