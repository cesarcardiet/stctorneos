#!/bin/bash
set -euo pipefail

APP=/var/www/stc-torneos
ARCHIVE=/tmp/stc-torneos.tgz
CREDS=/root/stc-vps-credentials.txt

test -f "$ARCHIVE"
test -f "$CREDS"

DB_NAME=$(grep '^DB name:' "$CREDS" | awk '{print $3}')
DB_USER=$(grep '^DB user:' "$CREDS" | awk '{print $3}')
DB_PASS=$(grep '^DB pass:' "$CREDS" | awk '{print $3}')

rm -rf /tmp/stc-extract
mkdir -p /tmp/stc-extract
tar -xzf "$ARCHIVE" -C /tmp/stc-extract

rsync -a --delete \
  --exclude=vendor \
  --exclude=node_modules \
  --exclude=storage/logs \
  --exclude=storage/framework/sessions \
  --exclude=storage/framework/cache \
  --exclude=storage/framework/views \
  /tmp/stc-extract/stc-torneos-system/ "$APP/"

cp "$APP/.env.example" "$APP/.env"
sed -i "s/^APP_ENV=.*/APP_ENV=production/" "$APP/.env"
sed -i "s/^APP_DEBUG=.*/APP_DEBUG=false/" "$APP/.env"
sed -i "s|^APP_URL=.*|APP_URL=https://stctorneos.com|" "$APP/.env"
sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" "$APP/.env"
sed -i "s/^# DB_HOST=.*/DB_HOST=127.0.0.1/" "$APP/.env"
sed -i "s/^# DB_PORT=.*/DB_PORT=3306/" "$APP/.env"
sed -i "s/^# DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" "$APP/.env"
sed -i "s/^# DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" "$APP/.env"
sed -i "s/^# DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" "$APP/.env"

mkdir -p "$APP/storage/framework/cache/data" \
  "$APP/storage/framework/sessions" \
  "$APP/storage/framework/views" \
  "$APP/storage/logs" \
  "$APP/bootstrap/cache"

cd "$APP"
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

PHP_FPM_USER=$(grep -E '^user\s*=' /etc/php-fpm.d/www.conf 2>/dev/null | awk '{print $3}' | tr -d ';' || true)
PHP_FPM_USER=${PHP_FPM_USER:-apache}
chown -R "$PHP_FPM_USER:$PHP_FPM_USER" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
systemctl reload php-fpm nginx

echo "DEPLOY_OK"
