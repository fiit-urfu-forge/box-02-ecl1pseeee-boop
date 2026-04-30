FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        bash git curl libzip-dev oniguruma-dev libpq-dev autoconf gcc g++ make linux-headers \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-cache autoconf gcc g++ make linux-headers

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php-fpm"]
