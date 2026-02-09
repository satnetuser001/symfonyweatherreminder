# For correct container work, current host system user must be set in
# compose.yaml, otherwise in "project" directory owner will be root.
# Also, the time zone environment variable must be set in compose.yam to
# install php without interactive questionsl.

# base docker image
FROM ubuntu:latest

# install system dependencies
RUN apt-get update && apt-get install -y \
    software-properties-common \
    curl \
    unzip \
    p7zip-full \
    git\
    tree

# add PHP repository
RUN add-apt-repository -y ppa:ondrej/php

# build argument for PHP version
ARG PHP_VERSION

# install PHP extensions required for Composer and Laravel installation
RUN apt-get update && apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-tokenizer \
    php${PHP_VERSION}-common \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-pcov \
    php${PHP_VERSION}-redis

# build argument for Composer version
ARG COMPOSER_VERSION

# install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer${COMPOSER_VERSION};

# build argument for Node.js version
ARG NODE_VERSION

# add Node.js repository (for npm)
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -

# install Node.js, which includes npm
RUN apt-get update && apt-get install -y nodejs

# clearing cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# set and create a working directory in image
WORKDIR /project

# infinite task to prevent container from stopping
ENTRYPOINT ["sleep", "infinity"]
