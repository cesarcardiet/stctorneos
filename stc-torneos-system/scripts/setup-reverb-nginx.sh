#!/bin/bash
set -euo pipefail

CONF=/etc/nginx/conf.d/stc-torneos.conf
APP=/var/www/stc-torneos

if ! grep -q 'location /app' "$CONF"; then
  cp "$CONF" "${CONF}.bak-reverb-$(date +%Y%m%d%H%M%S)"
  awk '
    BEGIN { inserted=0 }
    /location \/ \{/ && inserted==0 {
      print "    location /app {"
      print "        proxy_http_version 1.1;"
      print "        proxy_set_header Host $http_host;"
      print "        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;"
      print "        proxy_set_header X-Forwarded-Proto $scheme;"
      print "        proxy_set_header Upgrade $http_upgrade;"
      print "        proxy_set_header Connection \"Upgrade\";"
      print "        proxy_pass http://127.0.0.1:8080;"
      print "        proxy_read_timeout 60m;"
      print "        proxy_send_timeout 60m;"
      print "    }"
      print ""
      inserted=1
    }
    { print }
  ' "$CONF" > /tmp/stc-nginx-reverb.conf
  mv /tmp/stc-nginx-reverb.conf "$CONF"
  nginx -t
  systemctl reload nginx
  echo "NGINX_OK"
else
  echo "NGINX_ALREADY"
fi

cd "$APP"
php artisan config:clear
php artisan config:cache
php artisan route:cache

systemctl daemon-reload
systemctl enable stc-reverb.service
systemctl restart stc-reverb.service
sleep 1
systemctl is-active stc-reverb.service
ss -lntp | grep ':8080' || true
echo "REVERB_SETUP_OK"
