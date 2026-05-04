FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app/apps/php

COPY apps/php/composer.json /app/apps/php/composer.json
RUN composer install --no-interaction --prefer-dist --no-progress

COPY . /app

RUN composer install --no-interaction --prefer-dist --no-progress

ENV CATALOGA_REGISTRY_PATH=/app/registry
ENV CATALOGA_RUNTIME_PATH=/app/.cataloga

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
