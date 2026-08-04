# syntax=docker/dockerfile:1.7

FROM node:24-bookworm-slim AS node-runtime

FROM php:8.4-fpm-bookworm AS php-base

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        imagemagick \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        nginx \
        tesseract-ocr \
        tesseract-ocr-spa \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

FROM php-base AS builder

COPY --from=node-runtime /usr/local/ /usr/local/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY backend/ ./

RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && npm ci \
    && npm run build \
    && rm -rf node_modules

FROM php-base AS production

WORKDIR /var/www/html

COPY --from=builder /var/www/html /var/www/html
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/entrypoint.sh /usr/local/bin/ojev-entrypoint

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod 755 /usr/local/bin/ojev-entrypoint \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

EXPOSE 80

ENTRYPOINT ["ojev-entrypoint"]
CMD ["web"]
