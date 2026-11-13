<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Setiap menit: batalkan order pending yang sudah lewat batas waktu bayar.
// Jalankan saat development dengan: php artisan schedule:work
Schedule::command('orders:expire')->everyMinute();
