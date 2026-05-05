FROM composer:2 AS deps

WORKDIR /app/apps/php
COPY apps/php/composer.json apps/php/composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM php:8.3-cli AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY .git /app/.git
COPY cataloga.yaml /app/cataloga.yaml
COPY domain-packs /app/domain-packs
COPY registry /app/registry
COPY apps/php /app/apps/php
COPY --from=deps /app/apps/php/vendor /app/apps/php/vendor

ENV CATALOGA_REGISTRY_PATH=/app/registry
ENV CATALOGA_RUNTIME_PATH=/app/.cataloga

EXPOSE 8080

WORKDIR /app/apps/php
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
