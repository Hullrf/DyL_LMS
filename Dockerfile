# Imagen oficial de PHP para controlar la version exacta (Nixpacks fijaba 8.2.27, vulnerable
# a CVE-2026-109618/109619). Para subir de parche basta cambiar el tag de php:.

FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.2.35-fpm-alpine
ADD https://github.com/mlocati/docker-php-extension-installer/releases/download/2.7.20/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
 && apk add --no-cache nginx gettext git unzip \
 && install-php-extensions bcmath exif gd intl mysqli pdo_mysql zip opcache pcntl sockets
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader
COPY . .
COPY --from=assets /app/public/build public/build
RUN composer dump-autoload --no-dev --no-scripts --optimize \
 && cp nginx.template.conf /etc/nginx/nginx.template.conf

CMD ["sh", "start.sh"]
