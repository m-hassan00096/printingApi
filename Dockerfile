# syntax=docker/dockerfile:1

# Laravel API: PHP 8.3 + Apache, listening on port 80 for Coolify.
FROM php:8.3-apache-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libonig-dev libzip-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath exif gd intl mbstring opcache pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers \
    && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' \
        /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY . .

# Allow Laravel's Composer scripts to register/discover its package providers.
# Avoid config:cache and migrations at image-build time: runtime secrets/DB come from Coolify.
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress

# Vite assets are built separately; the application container needs no Node.js.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json .npmrc vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY --from=app /var/www/html/vendor ./vendor
RUN npm install --include=dev --no-audit --no-fund \
    && npm run build

FROM app AS production
COPY --from=assets /app/public/build /var/www/html/public/build

RUN mkdir -p \
        storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80
CMD ["sh", "/var/www/html/start.sh"]
