#!/bin/sh
# Arranque del servicio web en Railway (mismo orden que el startCommand anterior de railway.toml).
set -e

mkdir -p storage/app/tmp
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan storage:link --force

# El volumen de Railway se monta como root en storage/app/public; php-fpm corre como www-data.
# Se hace despues de los comandos artisan para que los archivos que crearon queden escribibles.
chown -R www-data:www-data storage bootstrap/cache

envsubst '${PORT}' < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf
php-fpm -D
exec nginx -c /etc/nginx/nginx.conf
