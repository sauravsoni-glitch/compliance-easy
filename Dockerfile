FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev unzip libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql zip curl > /dev/null \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
