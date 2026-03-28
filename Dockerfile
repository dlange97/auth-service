FROM php:8.3-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache icu-dev libzip-dev openssl bash rabbitmq-c rabbitmq-c-dev $PHPIZE_DEPS \
    && pecl install amqp pcov \
    && docker-php-ext-enable amqp \
    && docker-php-ext-enable pcov \
    && docker-php-ext-install intl pdo_mysql zip opcache \
    && apk del $PHPIZE_DEPS rabbitmq-c-dev

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

COPY composer.json composer.lock* ./

COPY . .

RUN mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data var config/jwt

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
