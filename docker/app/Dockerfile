# Imagen de desarrollo de la aplicación.
# PHP 8.3 según CLAUDE.md §2 (ADR 0001). El despliegue productivo usa una imagen
# distinta, sin dependencias de desarrollo y con OPcache preconfigurado.
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libonig-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip bcmath opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# El servidor embebido de PHP solo es aceptable en desarrollo.
EXPOSE 8080
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
