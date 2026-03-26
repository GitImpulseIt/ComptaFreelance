FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    oniguruma-dev

RUN docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    pgsql \
    intl \
    mbstring \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

RUN mkdir -p storage/logs storage/cache \
    && chown -R www-data:www-data storage

EXPOSE 9000
CMD ["php-fpm"]
