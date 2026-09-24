# Dockerfile referensi untuk backend Laravel 13 (PHP 8.4)
# Diletakkan di root project Laravel

FROM php:8.4-fpm-alpine

# Dependency sistem + ekstensi PHP yang umum dibutuhkan Laravel + PostgreSQL
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        zip \
        unzip \
        git \
        curl \
    && docker-php-ext-install pdo pdo_pgsql zip pcntl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# Izin folder storage & cache (wajib untuk Laravel)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
