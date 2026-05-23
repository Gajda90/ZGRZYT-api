FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    libonig-dev \
    npm \
    zip

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip \
    mbstring \
    exif \
    pcntl \
    intl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN touch database/database.sqlite

RUN composer install --no-dev --optimize-autoloader

RUN npm install && npm run build

RUN php artisan l5-swagger:generate

EXPOSE 10000

CMD php artisan config:clear && php artisan route:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=10000

