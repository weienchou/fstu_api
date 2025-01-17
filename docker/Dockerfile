FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache --repository http://dl-cdn.alpinelinux.org/alpine/edge/community/ --allow-untrusted gnu-libiconv
ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php

ENV PHP_MEMORY_LIMIT=1G
ENV PHP_UPLOAD_MAX_FILESIZE: 512M
ENV PHP_POST_MAX_SIZE: 512M

RUN docker-php-ext-install pdo

RUN apk add --no-cache libpng libpng-dev && docker-php-ext-install gd && apk del libpng-dev

RUN apk update \
    && apk upgrade \
    && apk add --no-cache \
        freetype \
        libpng \
        libjpeg-turbo \
        freetype-dev \
        libpng-dev \
        jpeg-dev \
        libwebp-dev \
        libjpeg \
        libjpeg-turbo-dev

RUN docker-php-ext-configure gd \
        --with-freetype=/usr/lib/ \
        --with-jpeg=/usr/lib/ \
        --with-webp=/usr

RUN NUMPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) \
    && docker-php-ext-install -j${NUMPROC} gd


RUN apk add --no-cache sqlite-libs
RUN apk add --no-cache icu sqlite git openssh zip
RUN apk add --no-cache --virtual .build-deps icu-dev libxml2-dev sqlite-dev curl-dev
RUN docker-php-ext-install \
        bcmath \
        curl \
        ctype \
        intl \
        pdo \
        mysqli \
        pdo_mysql \
        xml
RUN apk del .build-deps

RUN docker-php-ext-enable pdo_mysql

# Add xdebug
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS
RUN apk add --update linux-headers
RUN pecl install xdebug-3.4.0
RUN docker-php-ext-enable xdebug
RUN apk del -f .build-deps

# Configure Xdebug
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.idekey=sublime.xdebug" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.log=/var/www/html/xdebug.log" >> /usr/local/etc/php/conf.d/xdebug.ini
