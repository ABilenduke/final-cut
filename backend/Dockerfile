# ── Base ─────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    postgresql-dev \
    linux-headers \
    $PHPIZE_DEPS \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && docker-php-ext-install pdo_pgsql pcntl \
  && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize

# ── Development ──────────────────────────────
FROM base AS development
RUN composer install --prefer-dist
EXPOSE 9000
CMD ["php-fpm"]

# ── Production ───────────────────────────────
FROM base AS production
RUN php artisan config:cache \
  && php artisan route:cache \
  && php artisan view:cache
EXPOSE 9000
CMD ["php-fpm"]
