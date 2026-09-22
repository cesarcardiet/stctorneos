#!/bin/bash
set -euo pipefail
APP=/var/www/stc-torneos
ARCHIVE=/tmp/stc-torneos.tgz
CREDS=/root/stc-vps-credentials.txt

if [[ ! -f "$ARCHIVE" ]]; then
  echo "Missing $ARCHIVE"
  exit 1
fi

if [[ -f "$APP/.env" ]]; then
  cp "$APP/.env" /tmp/stc.env.bak
elif [[ -f /tmp/stc.env.bak ]]; then
  :
elif [[ -f "$CREDS" ]]; then
  DB_NAME=$(grep '^DB name:' "$CREDS" | awk '{print $3}')
  DB_USER=$(grep '^DB user:' "$CREDS" | awk '{print $3}')
  DB_PASS=$(grep '^DB pass:' "$CREDS" | awk '{print $3}')
  cp "$APP/.env.example" /tmp/stc.env.bak 2>/dev/null || true
else
  echo "No .env backup and no credentials file."
  exit 1
fi

rm -rf /tmp/stc-extract
mkdir -p /tmp/stc-extract
tar -xzf "$ARCHIVE" -C /tmp/stc-extract

rsync -a --delete \
  --exclude=.env \
  --exclude=vendor \
  --exclude=node_modules \
  --exclude=storage/logs \
  --exclude=storage/framework/sessions \
  --exclude=storage/framework/cache \
  --exclude=storage/framework/views \
  --exclude=storage/app/private \
  --exclude=storage/app/public \
  --exclude=public/images/delegations \
  --exclude=public/images/teams \
  --exclude=public/images/players \
  --exclude=public/images/tournaments \
  --exclude=public/images/categories \
  /tmp/stc-extract/stc-torneos-system/ "$APP/"

if [[ -f /tmp/stc.env.bak ]]; then
  cp /tmp/stc.env.bak "$APP/.env"
fi

mkdir -p "$APP/storage/framework/cache/data" \
  "$APP/storage/framework/sessions" \
  "$APP/storage/framework/views" \
  "$APP/storage/logs" \
  "$APP/bootstrap/cache"

cd "$APP"
if [[ -f "$CREDS" ]] && grep -q '^DB pass:' "$CREDS"; then
  DB_NAME=$(grep '^DB name:' "$CREDS" | awk '{print $3}')
  DB_USER=$(grep '^DB user:' "$CREDS" | awk '{print $3}')
  DB_PASS=$(grep '^DB pass:' "$CREDS" | awk '{print $3}')
  sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" "$APP/.env"
  sed -i "s/^# DB_HOST=.*/DB_HOST=127.0.0.1/" "$APP/.env"
  sed -i "s/^# DB_PORT=.*/DB_PORT=3306/" "$APP/.env"
  sed -i "s/^# DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" "$APP/.env"
  sed -i "s/^# DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" "$APP/.env"
  sed -i "s/^# DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" "$APP/.env"
  sed -i "s/^DB_HOST=.*/DB_HOST=127.0.0.1/" "$APP/.env"
  sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" "$APP/.env"
  sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" "$APP/.env"
  sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" "$APP/.env"
  sed -i "s|^APP_URL=.*|APP_URL=https://stctorneos.com|" "$APP/.env"
  sed -i "s/^APP_ENV=.*/APP_ENV=production/" "$APP/.env"
  sed -i "s/^APP_DEBUG=.*/APP_DEBUG=false/" "$APP/.env"
fi

composer install --no-dev --optimize-autoloader --no-interaction
if ! grep -q '^APP_KEY=base64:' "$APP/.env" 2>/dev/null; then
  php artisan key:generate --force
fi
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# AlmaLinux/RHEL php-fpm runs as apache; Debian/Ubuntu often uses www-data or nginx.
PHP_FPM_USER=$(grep -E '^user\s*=' /etc/php-fpm.d/www.conf 2>/dev/null | awk '{print $3}' | tr -d ';' || true)
PHP_FPM_USER=${PHP_FPM_USER:-apache}
chown -R "$PHP_FPM_USER:$PHP_FPM_USER" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
if [[ -d "$APP/public/build" ]]; then
  chown -R "$PHP_FPM_USER:$PHP_FPM_USER" "$APP/public/build"
  chmod -R 755 "$APP/public/build"
fi

# Uploaded media must stay writable by php-fpm and survive rsync --delete excludes.
mkdir -p \
  "$APP/public/images/tournaments" \
  "$APP/public/images/categories" \
  "$APP/public/images/delegations" \
  "$APP/public/images/teams" \
  "$APP/public/images/players" \
  "$APP/public/images/players/docs"
chown -R "$PHP_FPM_USER:$PHP_FPM_USER" \
  "$APP/public/images/tournaments" \
  "$APP/public/images/categories" \
  "$APP/public/images/delegations" \
  "$APP/public/images/teams" \
  "$APP/public/images/players"
chmod -R ug+rwx \
  "$APP/public/images/tournaments" \
  "$APP/public/images/categories" \
  "$APP/public/images/delegations" \
  "$APP/public/images/teams" \
  "$APP/public/images/players"

systemctl reload php-fpm
systemctl reload nginx

echo "DEPLOY_OK"
