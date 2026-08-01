FROM php:8.3-fpm-alpine

RUN apk add --no-cache fcgi libcurl \
    && apk add --no-cache --virtual .build-deps curl-dev \
    && docker-php-ext-install curl \
    && apk del .build-deps \
    && sed -i 's|^;ping.path = /ping|ping.path = /ping|' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

COPY src ./

EXPOSE 9000
