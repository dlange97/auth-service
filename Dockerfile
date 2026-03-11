FROM php:8.3-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache icu-dev libzip-dev openssl bash \
    && docker-php-ext-install intl pdo_mysql zip opcache

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist 2>/dev/null || true

COPY . .

RUN mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data var config/jwt

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
