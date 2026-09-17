#!/bin/sh
set -eu

PORT="${PORT:-10000}"
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "==> Ejecutando migraciones..."
php artisan migrate --force

echo "==> Creando storage link si aplica..."
php artisan storage:link >/dev/null 2>&1 || true

echo "==> Cacheando configuración..."
php artisan config:cache

echo "==> Iniciando Apache en puerto ${PORT}..."
exec apache2-foreground
