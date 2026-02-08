FROM php:8.2-fpm

# System deps
RUN apt-get update && apt-get install -y \
    git unzip curl libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
