ARG PHP_VERSION=""
FROM php:7.0-fpm-alpine

RUN apk update; \
    apk upgrade;

RUN docker-php-ext-install mysqli
