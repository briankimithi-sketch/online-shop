
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

# The built-in `php artisan serve` server forks a worker process for each HTTP
# request. Those forked workers do NOT reliably inherit the environment
# variables that were set on the container via `docker run -e`, even though the
# parent process sees them fine. Laravel's DotEnv loader reads `.env` for every
# request, so we write the runtime variables into `.env` here to guarantee the
# web workers see them.
CMD ["sh", "-c", "if [ -n \"${APP_KEY}\" ]; then echo \"APP_KEY=${APP_KEY}\" >> .env; fi; if [ -n \"${APP_ENV}\" ]; then echo \"APP_ENV=${APP_ENV}\" >> .env; fi; if [ -n \"${APP_DEBUG}\" ]; then echo \"APP_DEBUG=${APP_DEBUG}\" >> .env; fi; if [ -n \"${APP_URL}\" ]; then echo \"APP_URL=${APP_URL}\" >> .env; fi; if [ -n \"${LOG_CHANNEL}\" ]; then echo \"LOG_CHANNEL=${LOG_CHANNEL}\" >> .env; fi; if [ -n \"${LOG_LEVEL}\" ]; then echo \"LOG_LEVEL=${LOG_LEVEL}\" >> .env; fi; if [ -n \"${SESSION_DRIVER}\" ]; then echo \"SESSION_DRIVER=${SESSION_DRIVER}\" >> .env; fi; if [ -n \"${DB_CONNECTION}\" ]; then echo \"DB_CONNECTION=${DB_CONNECTION}\" >> .env; fi; if [ -n \"${DB_HOST}\" ]; then echo \"DB_HOST=${DB_HOST}\" >> .env; fi; if [ -n \"${DB_PORT}\" ]; then echo \"DB_PORT=${DB_PORT}\" >> .env; fi; if [ -n \"${DB_DATABASE}\" ]; then echo \"DB_DATABASE=${DB_DATABASE}\" >> .env; fi; if [ -n \"${DB_USERNAME}\" ]; then echo \"DB_USERNAME=${DB_USERNAME}\" >> .env; fi; if [ -n \"${DB_PASSWORD}\" ]; then echo \"DB_PASSWORD=${DB_PASSWORD}\" >> .env; fi; exec php artisan serve --host=0.0.0.0 --port=8000"]
