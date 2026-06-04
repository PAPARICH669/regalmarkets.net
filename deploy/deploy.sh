#!/usr/bin/env bash
#
# Regal Markets — one-command production deploy for Ubuntu (20.04/22.04/24.04).
# Frontend  -> https://regalmarkets.net        (Next.js via PM2, Nginx reverse proxy)
# API       -> https://api.regalmarkets.net     (Laravel 9 via PHP-FPM)
#
# Safe & additive: it only ADDS new Nginx sites + a new DB; it does NOT touch any
# existing site (e.g. propertynir). Idempotent — safe to re-run.
#
# USAGE (run on the VPS as root, from inside the cloned repo):
#   cd /var/www/regalmarkets
#   sudo EMAIL=you@example.com DB_PASSWORD='choose-a-strong-pass' bash deploy/deploy.sh
#
# Optional env overrides:
#   DOMAIN=regalmarkets.net  API_DOMAIN=api.regalmarkets.net
#   PHP_VER=8.2  NODE_MAJOR=20  APP_DIR=/var/www/regalmarkets  FRONTEND_PORT=3000
#   SKIP_SSL=1   (skip certbot, e.g. if DNS not pointed yet)
#
set -euo pipefail

DOMAIN="${DOMAIN:-regalmarkets.net}"
API_DOMAIN="${API_DOMAIN:-api.regalmarkets.net}"
APP_DIR="${APP_DIR:-/var/www/regalmarkets}"
PHP_VER="${PHP_VER:-8.2}"
NODE_MAJOR="${NODE_MAJOR:-20}"
FRONTEND_PORT="${FRONTEND_PORT:-3000}"
DB_NAME="${DB_NAME:-regal_markets}"
DB_USER="${DB_USER:-regal}"
DB_PASSWORD="${DB_PASSWORD:-}"
EMAIL="${EMAIL:-}"
SKIP_SSL="${SKIP_SSL:-0}"

say() { echo -e "\n\033[1;33m==> $*\033[0m"; }
need_root() { [ "$(id -u)" = "0" ] || { echo "Run as root (sudo)."; exit 1; }; }
need_root

[ -n "$DB_PASSWORD" ] || { echo "Set DB_PASSWORD=... (a strong DB password)"; exit 1; }
[ -f "$APP_DIR/backend/artisan" ] || { echo "Run from the repo: expected $APP_DIR/backend/artisan"; exit 1; }

export DEBIAN_FRONTEND=noninteractive

say "1/9 Base packages + PHP ${PHP_VER} (ondrej PPA) + Node ${NODE_MAJOR}"
apt-get update -y
apt-get install -y software-properties-common curl unzip git ca-certificates gnupg lsb-release rsync
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y nginx mariadb-server \
  php${PHP_VER}-fpm php${PHP_VER}-cli php${PHP_VER}-mysql php${PHP_VER}-mbstring \
  php${PHP_VER}-xml php${PHP_VER}-bcmath php${PHP_VER}-curl php${PHP_VER}-zip \
  php${PHP_VER}-gd php${PHP_VER}-intl
# Composer
if ! command -v composer >/dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
# Node + PM2
if ! command -v node >/dev/null || [ "$(node -v | cut -dv -f2 | cut -d. -f1)" -lt "$NODE_MAJOR" ]; then
  curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
  apt-get install -y nodejs
fi
npm install -g pm2 >/dev/null 2>&1 || true

say "2/9 Database: ${DB_NAME} + user ${DB_USER}"
systemctl enable --now mariadb
mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

say "3/9 Backend (Laravel) — composer install, .env, migrate, seed"
cd "$APP_DIR/backend"
composer install --no-dev --optimize-autoloader --no-interaction
if [ ! -f .env ]; then cp .env.example .env; fi
# write/refresh key production values
set_env() { local k="$1" v="$2"; if grep -q "^${k}=" .env; then sed -i "s|^${k}=.*|${k}=${v}|" .env; else echo "${k}=${v}" >> .env; fi; }
set_env APP_NAME "RegalMarkets"
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_TIMEZONE Asia/Kuala_Lumpur
set_env APP_URL "https://${API_DOMAIN}"
set_env FRONTEND_URL "https://${DOMAIN}"
set_env SANCTUM_STATEFUL_DOMAINS "${DOMAIN}"
set_env SESSION_DOMAIN ".${DOMAIN}"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "${DB_NAME}"
set_env DB_USERNAME "${DB_USER}"
set_env DB_PASSWORD "${DB_PASSWORD}"
grep -q "^APP_KEY=base64" .env || php artisan key:generate --force
php artisan migrate --force --seed
php artisan storage:link || true
php artisan config:cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

say "4/9 Frontend (Next.js) — install + build"
cd "$APP_DIR/frontend"
printf 'NEXT_PUBLIC_API_URL=https://%s/api\nNEXT_PUBLIC_APP_NAME=Regal Markets\n' "$API_DOMAIN" > .env.production
npm ci
npm run build

say "5/9 PM2 — run Next.js on 127.0.0.1:${FRONTEND_PORT}"
pm2 delete regal-frontend >/dev/null 2>&1 || true
PORT=${FRONTEND_PORT} pm2 start npm --name regal-frontend -- run start
pm2 save
pm2 startup systemd -u root --hp /root >/dev/null 2>&1 || true

say "6/9 Nginx — API server block (${API_DOMAIN})"
cat > /etc/nginx/sites-available/regal-api.conf <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${API_DOMAIN};
    root ${APP_DIR}/backend/public;
    index index.php;
    client_max_body_size 12M;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX

say "7/9 Nginx — frontend server block (${DOMAIN})"
cat > /etc/nginx/sites-available/regal-frontend.conf <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};

    location / {
        proxy_pass http://127.0.0.1:${FRONTEND_PORT};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/regal-api.conf /etc/nginx/sites-enabled/regal-api.conf
ln -sf /etc/nginx/sites-available/regal-frontend.conf /etc/nginx/sites-enabled/regal-frontend.conf
nginx -t && systemctl reload nginx

say "8/9 Scheduler (cron: ROI / ranks / maintenance every minute)"
CRON="* * * * * cd ${APP_DIR}/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
( crontab -l 2>/dev/null | grep -v "regalmarkets/backend && .*schedule:run" ; echo "$CRON" ) | crontab -

say "9/9 SSL (Let's Encrypt)"
if [ "$SKIP_SSL" = "1" ]; then
  echo "SKIP_SSL=1 -> skipping certbot. Run later once DNS is pointed:"
  echo "  certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} -d ${API_DOMAIN} -m ${EMAIL} --agree-tos -n"
else
  [ -n "$EMAIL" ] || { echo "Set EMAIL=you@example.com for SSL (or SKIP_SSL=1)"; exit 1; }
  apt-get install -y certbot python3-certbot-nginx
  certbot --nginx -d "${DOMAIN}" -d "www.${DOMAIN}" -d "${API_DOMAIN}" -m "${EMAIL}" --agree-tos -n --redirect || {
    echo "Certbot failed — likely DNS not yet pointing to this server. Sites are live on HTTP."
    echo "Re-run later: certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} -d ${API_DOMAIN} -m ${EMAIL} --agree-tos -n --redirect"
  }
fi

echo -e "\n\033[1;32mDONE.\033[0m  Frontend: https://${DOMAIN}   API: https://${API_DOMAIN}/api"
echo "Admin login: admin / password  (TUKAR password ini segera!)"
echo "Re-deploy after code changes:  bash deploy/update.sh"
