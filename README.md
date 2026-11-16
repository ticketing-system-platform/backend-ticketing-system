# TiketEvent — Backend (Laravel API)

REST API untuk aplikasi tiket event: auth Sanctum, CRUD event & jenis tiket, checkout dengan penguncian kuota,
Midtrans Snap + webhook, email via queue, dan check-in QR.

Panduan lengkap langkah demi langkah ada di [README utama](../README.md).

```bash
composer install
cp .env.example .env && php artisan key:generate   # isi DB_*, MAIL_*, MIDTRANS_*
php artisan migrate --seed
php artisan storage:link
php artisan serve          # API: http://localhost:8000/api
php artisan queue:work     # terminal lain: kirim email
php artisan schedule:work  # terminal lain: expire order otomatis
```
