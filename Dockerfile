FROM node:20 AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY postcss.config.js ./
COPY tailwind.config.js ./
RUN npm run build

FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libpq-dev zip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY --from=assets /app/public/build /var/www/public/build

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD sh -c "php artisan config:cache || true; \
    php artisan migrate --force || true; \
    php artisan view:cache || true; \
    php artisan storage:link || true; \
    php -S 0.0.0.0:${PORT:-10000} -t public"
