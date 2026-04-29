# syntax=docker/dockerfile:1.6
# ---------------------------------------------------------------------------
# PHP 8.3 FPM image for Laravel 11 (app + worker + scheduler)
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1

# System deps required by Laravel + drivers used in the project.
RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        autoconf \
        g++ \
        make \
        linux-headers \
        postgresql-client \
        redis \
        tini \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        sockets \
        zip \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && apk del --no-network autoconf g++ make linux-headers

# Composer via upstream image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Opcache tuned for production; can be overridden via env.
RUN { \
      echo "opcache.enable=1"; \
      echo "opcache.enable_cli=0"; \
      echo "opcache.memory_consumption=192"; \
      echo "opcache.interned_strings_buffer=16"; \
      echo "opcache.max_accelerated_files=20000"; \
      echo "opcache.validate_timestamps=\${PHP_OPCACHE_VALIDATE_TIMESTAMPS}"; \
      echo "opcache.revalidate_freq=2"; \
      echo "opcache.jit=tracing"; \
      echo "opcache.jit_buffer_size=64M"; \
    } > /usr/local/etc/php/conf.d/zz-opcache.ini \
    && { \
      echo "memory_limit=256M"; \
      echo "upload_max_filesize=16M"; \
      echo "post_max_size=16M"; \
      echo "expose_php=Off"; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# Non-root user for safety; mount host code into /var/www/html in dev.
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app \
    && adduser -u ${UID} -G app -D -s /bin/bash app \
    && chown -R app:app /var/www

USER app

EXPOSE 9000

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php-fpm", "-F"]
