# build argument for PHP version
ARG PHP_VERSION

# base docker image
FROM php:${PHP_VERSION}fpm

# install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    libicu-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql intl

# build argument for Xdebug version
ARG XDEBUG_VERSION

# install and enable Xdebug PHP extension
RUN pecl install xdebug${XDEBUG_VERSION} \
    && docker-php-ext-enable xdebug

# create xdebug logs directory
RUN mkdir /xdebug-logs

# TODO: refactor code for Composer, PHP-CS-Fixer, PHP_CodeSniffer
# install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# install PHP-CS-Fixer
RUN curl -L https://cs.symfony.com/download/php-cs-fixer-v3.phar -o /usr/local/bin/php-cs-fixer \
    && chmod +x /usr/local/bin/php-cs-fixer

# install PHP_CodeSniffer
RUN curl -OL https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar \
    && curl -OL https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar \
    && chmod +x phpcs.phar phpcbf.phar \
    && mv phpcs.phar /usr/local/bin/phpcs \
    && mv phpcbf.phar /usr/local/bin/phpcbf

# set and create a working directory in image
WORKDIR /project

# declare expose port
EXPOSE 9000