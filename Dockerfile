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

# Provide an empty environment file for Laravel when no .env is bundled.
RUN touch .env

ARG INSTALL_DEV=false

# Retry composer install to tolerate transient GitHub rate limiting (HTTP 429)
# from codeload.github.com when fetching dist archives without an OAuth token.
# Anonymous downloads are rate-limited per IP, so a few spaced-out attempts get
# through once the limit window clears.
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
        echo "composer install failed (attempt $attempt/$max_attempts); retrying..."; \
        attempt=$((attempt + 1)); \
        sleep $((attempt * 15)); \
    done

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
