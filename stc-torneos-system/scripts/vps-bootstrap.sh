#!/bin/bash
# AlmaLinux 9 — instalación inicial STC Torneos + Nginx + PHP 8.2 + MariaDB + Certbot
set -euo pipefail

APP=/var/www/stc-torneos
DOMAIN="${STC_DOMAIN:-stctorneos.com}"
DB_NAME="${STC_DB_NAME:-stc_torneos_system}"
DB_USER="${STC_DB_USER:-stc_app}"
DB_PASS="${STC_DB_PASS:-$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)}"

echo "==> Actualizando sistema..."
dnf -y update

echo "==> Instalando paquetes base..."
dnf -y install epel-release
dnf -y install nginx mariadb-server git unzip tar rsync curl policycoreutils-python-utils
dnf -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm || true
dnf module reset php -y || true
dnf module enable php:remi-8.2 -y
dnf -y install php php-fpm php-cli php-mysqlnd php-xml php-mbstring php-zip php-gd php-intl php-opcache php-bcmath php-process

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v certbot >/dev/null 2>&1; then
  dnf -y install certbot python3-certbot-nginx
fi

echo "==> Servicios..."
systemctl enable --now nginx mariadb php-fpm
systemctl enable --now firewalld 2>/dev/null || true
if systemctl is-active firewalld >/dev/null 2>&1; then
  firewall-cmd --permanent --add-service=http
  firewall-cmd --permanent --add-service=https
  firewall-cmd --reload
fi

echo "==> MariaDB..."
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

mkdir -p "$APP"
PHP_FPM_USER=$(grep -E '^user\s*=' /etc/php-fpm.d/www.conf 2>/dev/null | awk '{print $3}' | tr -d ';' || true)
PHP_FPM_USER=${PHP_FPM_USER:-apache}
chown -R "$PHP_FPM_USER:$PHP_FPM_USER" "$APP" 2>/dev/null || true

cat >/etc/nginx/conf.d/stc-torneos.conf <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    root ${APP}/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 32M;
}
NGINX

nginx -t
systemctl reload nginx

cat >/root/stc-vps-credentials.txt <<CREDS
STC VPS credentials — $(date -Iseconds)
Domain: ${DOMAIN}
App path: ${APP}
DB name: ${DB_NAME}
DB user: ${DB_USER}
DB pass: ${DB_PASS}

Next steps:
1) Point DNS A records @ and www to this server IP.
2) Upload app tarball to /tmp/stc-torneos.tgz and run scripts/stc-update.sh
3) SSL: certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN} --redirect
CREDS
chmod 600 /root/stc-vps-credentials.txt

echo "BOOTSTRAP_OK"
echo "Credentials: /root/stc-vps-credentials.txt"
