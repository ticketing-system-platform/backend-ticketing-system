<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

/**
 * Semua komunikasi dengan Midtrans dikumpulkan di sini,
 * supaya controller tetap ringkas dan mudah dibaca.
 */
class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = (bool) config('midtrans.is_production');
        Config::$isSanitized = true; // bersihkan input sebelum dikirim
        Config::$is3ds = true; // aktifkan 3D Secure untuk kartu kredit
        // Kosong = Midtrans memakai Notification URL dari dashboard
        Config::$overrideNotifUrl = config('midtrans.notification_url');
    }

    /**
     * Minta Snap token untuk sebuah order.
     * Token ini dipakai React untuk membuka popup pembayaran (window.snap.pay).
     */
    public function createSnapToken(Order $order): string
    {
        $order->loadMissing('user', 'items.ticketType.event');

        $itemDetails = $order->items->map(fn (OrderItem $item) => [
            'id' => (string) $item->ticket_type_id,
            'price' => $item->price,
            'quantity' => $item->qty,
            // Midtrans membatasi nama item maksimal 50 karakter
            'name' => Str::limit($item->ticketType->event->title.' - '.$item->ticketType->name, 47),
        ])->all();

        $params = [
            'transaction_details' => [
                'order_id' => $order->order_code, // harus unik, tidak boleh dipakai ulang
                'gross_amount' => $order->total, // harus SAMA dengan jumlah price × quantity
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
            ],
            // Batas waktu pembayaran, sama dengan expires_at di tabel orders
            'expiry' => [
                'start_time' => $order->created_at->format('Y-m-d H:i:s O'),
                'unit' => 'minutes',
                'duration' => config('midtrans.expiry_minutes'),
            ],
        ];

        return Snap::getSnapToken($params);
    }

    /**
     * Pastikan notifikasi benar-benar dari Midtrans, bukan request palsu.
     * signature_key = sha512(order_id + status_code + gross_amount + server_key)
     *
     * Catatan: gross_amount dari Midtrans berupa string, mis. "150000.00".
     * Pakai apa adanya, JANGAN diubah menjadi angka.
     */
    public function isValidSignature(array $payload): bool
    {
        $expected = hash('sha512',
            ($payload['order_id'] ?? '').
            ($payload['status_code'] ?? '').
            ($payload['gross_amount'] ?? '').
            config('midtrans.server_key')
        );

        // hash_equals lebih aman daripada === untuk membandingkan hash
        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }
}
