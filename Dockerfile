
# Stage 1: Compile frontend assets
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

RUN npm run build


# Stage 2: Build the Laravel application
FROM php:8.2-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        bcmath \
        zip \
        xml \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Copy the compiled Vite assets into Laravel's public directory
COPY --from=frontend /app/public/build ./public/build

# Provide an empty environment file for Laravel when no .env is bundled.
RUN touch .env

ARG INSTALL_DEV=false

# Retry Composer installation to tolerate transient GitHub rate limiting.
RUN set -eu; \
    if [ "$INSTALL_DEV" = "true" ]; then \
        DEV_FLAG=""; \
    else \
        DEV_FLAG="--no-dev"; \
    fi; \
    attempt=1; \
    max_attempts=5; \
    until composer install \
            --no-interaction \
            --prefer-dist \
            --no-progress \
            --optimize-autoloader $DEV_FLAG; do \
        if [ "$attempt" -ge "$max_attempts" ]; then \
            echo "ERROR: composer install failed after $max_attempts attempts."; \
            exit 1; \
        fi; \
        echo "Composer install failed (attempt $attempt/$max_attempts); retrying..."; \
        attempt=$((attempt + 1)); \
        sleep $((attempt * 15)); \
    done

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
