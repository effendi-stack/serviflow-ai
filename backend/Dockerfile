FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libzip-dev libonig-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip mbstring pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader || true

EXPOSE 9000
CMD ["php-fpm"]
