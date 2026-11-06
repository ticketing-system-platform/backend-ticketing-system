<?php

// Konfigurasi Midtrans. Nilai asli disimpan di .env, JANGAN ditulis di kode.
return [
    'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    // Opsional. Jika diisi, webhook transaksi dari server ini dikirim ke URL ini,
    // bukan ke Notification URL di dashboard Midtrans. Berguna agar laptop (ngrok)
    // dan VPS bisa memakai satu akun Sandbox yang sama.
    'notification_url' => env('MIDTRANS_NOTIFICATION_URL'),

    // Lama waktu pembayaran sebelum order kedaluwarsa (menit)
    'expiry_minutes' => 30,
];
