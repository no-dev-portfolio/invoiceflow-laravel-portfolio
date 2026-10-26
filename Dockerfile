FROM php:8.4-cli-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libonig-dev libzip-dev libicu-dev libsqlite3-dev && docker-php-ext-install -j2 mbstring intl bcmath zip pdo_sqlite && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
RUN useradd -m -u 10001 appuser
COPY . /app

FROM base AS test
RUN composer install --prefer-dist --no-interaction && cp .env.example .env && chown -R appuser:appuser /app
USER appuser
CMD ["php", "artisan", "test", "--no-ansi"]

FROM base AS demo
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader && mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R appuser:appuser /app
USER appuser
EXPOSE 8000
ENTRYPOINT ["sh", "/app/docker/entrypoint.sh"]
