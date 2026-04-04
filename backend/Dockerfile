# syntax=docker/dockerfile:1

# ──────────────────────────────────────────────
# Laravel Backend API
# ──────────────────────────────────────────────
# PHP-FPM on Alpine — pinned to exact versions
# for reproducible, secure builds.
# ──────────────────────────────────────────────

# ── Stage 1: Composer dependency install ──────
FROM php:8.4.19-fpm-alpine3.23 AS vendor

RUN apk add --no-cache \
    libpq-dev \
    icu-dev \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    intl \
    opcache

COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev

# ── Stage 2: Production image ────────────────
FROM php:8.4.19-fpm-alpine3.23 AS production

# Patch base image vulnerabilities
RUN apk upgrade --no-cache

# Build deps (removed after ext install) vs runtime deps (kept)
RUN apk add --no-cache --virtual .build-deps \
    libpq-dev \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    intl \
    opcache \
    bcmath \
    pcntl \
    zip \
    gd \
    exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && apk add --no-cache \
    libpq \
    icu-libs \
    libzip \
    freetype \
    libjpeg-turbo \
    libpng

# ── PHP hardening ─────────────────────────────
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY <<'EOF' $PHP_INI_DIR/conf.d/security.ini
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /dev/stderr
session.cookie_httponly = On
session.cookie_secure = On
session.use_strict_mode = On
EOF

# ── OPcache tuning ────────────────────────────
COPY <<'EOF' $PHP_INI_DIR/conf.d/opcache.ini
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.jit = disable
EOF

# ── PHP-FPM pool hardening ────────────────────
RUN sed -i \
    -e 's/^listen = .*/listen = 9000/' \
    -e 's/^;clear_env = .*/clear_env = no/' \
    /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Copy application from vendor stage
COPY --from=vendor /var/www/html /var/www/html

# Ensure writable directories exist with correct permissions
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# ── Stage 3: Development image ────────────────
FROM production AS development

USER root

COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

# Re-enable timestamps for dev (file changes via volume mounts)
RUN sed -i 's/opcache.validate_timestamps = 0/opcache.validate_timestamps = 1/' \
    $PHP_INI_DIR/conf.d/opcache.ini

# Install dev dependencies (vendor stage only has --no-dev)
RUN composer install --no-interaction

USER www-data
