FROM php:8.3-fpm-alpine

# Extensions système
RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    oniguruma-dev

# Extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        intl \
        mbstring \
        zip \
        gd \
        opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Config PHP personnalisée
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# Permissions storage
RUN mkdir -p storage/logs storage/cache storage/uploads \
    && chown -R www-data:www-data storage

EXPOSE 9000
CMD ["php-fpm"]
