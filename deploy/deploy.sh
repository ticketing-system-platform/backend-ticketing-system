#!/usr/bin/env bash
# Deploy ulang setelah ada perubahan kode yang sudah di-push ke GitHub.
# Jalankan di VPS sebagai user deploy:
#   bash /var/www/tiket-event/deploy/deploy.sh
set -euo pipefail                 # berhenti di perintah pertama yang gagal

cd "$(dirname "$0")/.."           # pindah ke folder proyek (induk folder deploy/)

echo "==> 1/4 Ambil kode terbaru dari GitHub"
git pull --ff-only

echo "==> 2/4 Backend: dependency, migration, cache"
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize              # cache config, route, event, dan view

echo "==> 3/4 Frontend: build ulang"
cd ../frontend
npm ci
npm run build

echo "==> 4/4 Restart queue worker agar memakai kode baru"
cd ../backend
php artisan queue:restart

echo "Selesai! Aplikasi sudah memakai kode terbaru."
