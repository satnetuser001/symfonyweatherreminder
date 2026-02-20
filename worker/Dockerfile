# build argument for PHP version
ARG PHP_VERSION

# base docker image
FROM php:${PHP_VERSION}alpine

# Install system dependency for intl extension
RUN apk add --no-cache icu-dev

# Install core PHP extensions
RUN docker-php-ext-install intl pdo_mysql

# Install AMQP extension (RabbitMQ)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS rabbitmq-c-dev \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del .build-deps

# set and create a working directory in image
WORKDIR /project