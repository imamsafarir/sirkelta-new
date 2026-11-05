#!/bin/bash
set -e

echo "==> Pulling latest changes from Git..."
git pull origin main

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Building frontend assets..."
npm run build

echo "==> Syncing public directory to public_html..."
rsync -av --exclude="index.php" public/ ../public_html/

echo "==> Clearing and caching config/routes..."
php artisan optimize:clear
php artisan optimize

echo "==> Deployment completed successfully!"
