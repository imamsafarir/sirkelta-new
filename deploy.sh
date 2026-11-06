#!/bin/bash
set -e

echo "==> Pulling latest changes from Git..."
git pull origin main

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Syncing assets to public_html (preserving index.php)..."
# 1. Salin seluruh isi folder build (CSS, JS, manifest Vite)
cp -rf public/build ../public_html/

# 2. Salin aset statis tambahan jika ada (favicon, robots.txt, gambar, storage) tanpa menimpa index.php
for item in public/*; do
    filename=$(basename "$item")
    if [ "$filename" != "index.php" ] && [ "$filename" != "build" ]; then
        cp -rf "$item" ../public_html/
    fi
done

echo "==> Clearing and caching config/routes..."
php artisan optimize:clear
php artisan optimize

echo "==> Deployment completed successfully!"
