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
    libzip-dev \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    intl \
    opcache \
    zip

COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

ARG INSTALL_DEV_DEPS=false

RUN composer install \
    $([ "$INSTALL_DEV_DEPS" = "true" ] && echo "" || echo "--no-dev") \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize $([ "$INSTALL_DEV_DEPS" = "true" ] && echo "" || echo "--no-dev")

# ── Stage 2: Production image ────────────────
FROM php:8.4.19-fpm-alpine3.23 AS production

# Patch base image vulnerabilities
RUN apk upgrade --no-cache

# Build deps (removed after ext install) vs runtime deps (kept)
RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    gcc \
    g++ \
    make \
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

# Ensure writable directories exist with correct permissions. The
# admin-auth-events.log file is pre-created so the Fail2ban admin-login
# jail (mounting the shared backend-logs volume read-only) can boot
# without waiting for the first failed login event.
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && touch storage/logs/admin-auth-events.log \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod 0640 storage/logs/admin-auth-events.log

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# ── Stage 2b: E2E seeder (production + dev deps for seeding) ──
# Extends production with Composer and dev dependencies so that
# migrate:fresh --seed works (seeders need Faker). Used as a
# one-shot sidecar container — not a runtime image.
FROM production AS e2e-seeder

USER root
COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

# Install dev dependencies on top of the production vendor dir.
# --no-scripts skips Laravel's post-install hooks (package:discover
# etc.) which would otherwise write bootstrap/cache/*.php as root
# and block the www-data process that runs migrate:fresh --seed.
RUN composer install --no-interaction --no-scripts \
    && chown -R www-data:www-data /var/www/html/vendor /var/www/html/bootstrap/cache

USER www-data

CMD ["php", "artisan", "migrate:fresh", "--seed", "--force"]

# ── Stage 3: Development image ────────────────
FROM production AS development

USER root

COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

# Re-enable timestamps for dev (file changes via volume mounts)
RUN sed -i 's/opcache.validate_timestamps = 0/opcache.validate_timestamps = 1/' \
    $PHP_INI_DIR/conf.d/opcache.ini

# Create a dev user matching the host UID/GID so bind-mounted files
# and the vendor volume share the same ownership. This is scoped to
# the development stage only — production still runs as www-data.
ARG DEV_UID=1000
ARG DEV_GID=1000
RUN addgroup -g ${DEV_GID} devuser \
    && adduser -u ${DEV_UID} -G devuser -D -h /home/devuser devuser \
    && chown -R devuser:devuser storage bootstrap/cache vendor public

# Configure PHP-FPM pool to run workers as devuser instead of www-data.
# The FPM master process still starts as root (required for FPM), but
# all worker processes that handle requests run as devuser (UID 1000).
RUN sed -i \
    -e 's/^user = www-data/user = devuser/' \
    -e 's/^group = www-data/group = devuser/' \
    /usr/local/etc/php-fpm.d/www.conf

# Install dev dependencies as the dev user so the vendor volume
# is owned by the same UID as the host bind mount.
USER devuser

RUN composer install --no-interaction

# Dev entrypoint: starts as root to fix bind-mount ownership on
# storage dirs, then exec's php-fpm (which needs root for its master).
USER root
COPY docker/dev-entrypoint.sh /usr/local/bin/dev-entrypoint.sh
ENTRYPOINT ["dev-entrypoint.sh"]
CMD ["php-fpm"]
