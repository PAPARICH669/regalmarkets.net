#!/usr/bin/env bash
# Re-deploy after pulling new code. Run from the repo root on the VPS.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/regalmarkets}"
cd "$APP_DIR"
git pull --ff-only
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
chown -R www-data:www-data storage bootstrap/cache
cd ../frontend
npm ci
npm run build
pm2 restart regal-frontend
echo "Updated."
