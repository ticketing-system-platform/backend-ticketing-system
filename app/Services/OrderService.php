<?php

namespace App\Services;

use App\Mail\OrderExpiredMail;
use App\Mail\PaymentSuccessMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Logika bisnis order: checkout, menerima notifikasi pembayaran,
 * menerbitkan tiket, dan membatalkan order.
 */
class OrderService
{
    public function __construct(private MidtransService $midtrans) {}

    /**
     * Buat order baru + tahan kuota tiket, lalu minta Snap token ke Midtrans.
     *
     * @param  array<int, array{ticket_type_id: int, qty: int}>  $items
     */
    public function checkout(User $user, array $items): Order
    {
        $order = DB::transaction(function () use ($user, $items) {
            $total = 0;
            $lines = [];

            // Urutkan berdasarkan id supaya semua request mengunci baris
            // dengan urutan yang sama (menghindari deadlock)
            foreach (collect($items)->sortBy('ticket_type_id') as $item) {
                // lockForUpdate(): baris ticket_type dikunci sampai transaksi selesai.
                // Request lain yang membeli jenis tiket yang sama harus MENUNGGU,
                // sehingga tiket tidak terjual melebihi kuota (overselling).
                $ticketType = TicketType::with('event')
                    ->lockForUpdate()
                    ->findOrFail($item['ticket_type_id']);

                if ($ticketType->event->status !== 'published') {
                    throw ValidationException::withMessages([
                        'items' => "Tiket {$ticketType->name} belum dijual.",
                    ]);
                }

                if ($ticketType->remaining() < $item['qty']) {
                    throw ValidationException::withMessages([
                        'items' => "Sisa kuota tiket {$ticketType->name} tinggal {$ticketType->remaining()}.",
                    ]);
                }

                // Kuota langsung "ditahan" untuk order ini
                $ticketType->increment('sold', $item['qty']);

                $lines[] = [
                    'ticket_type_id' => $ticketType->id,
                    'qty' => $item['qty'],
                    'price' => $ticketType->price, // harga disalin saat checkout
                ];
                $total += $ticketType->price * $item['qty'];
            }

            $order = $user->orders()->create([
                'order_code' => $this->generateOrderCode(),
                'total' => $total,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(config('midtrans.expiry_minutes')),
            ]);
            $order->items()->createMany($lines);

            return $order;
        });

        // Snap token diminta DI LUAR transaksi supaya lock database
        // tidak tertahan selama menunggu respons HTTP dari Midtrans.
        try {
            $order->update(['snap_token' => $this->midtrans->createSnapToken($order)]);
        } catch (Throwable $e) {
            report($e); // catat ke storage/logs/laravel.log
            $this->cancel($order, 'failed', sendEmail: false);
            abort(502, 'Gagal terhubung ke Midtrans. Silakan coba lagi.');
        }

        return $order;
    }

    /**
     * Proses notifikasi (webhook) dari Midtrans.
     * Signature sudah diverifikasi di controller sebelum method ini dipanggil.
     */
    public function handleNotification(array $payload): void
    {
        DB::transaction(function () use ($payload) {
            // Kunci baris order: jika Midtrans mengirim notifikasi yang sama
            // dua kali bersamaan, keduanya diproses bergantian (tidak dobel).
            $order = Order::where('order_code', $payload['order_id'])->lockForUpdate()->first();

            if (! $order) {
                Log::warning('Notifikasi Midtrans untuk order yang tidak dikenal', $payload);

                return;
            }

            // IDEMPOTEN: order yang sudah lunas tidak diproses lagi,
            // jadi walaupun webhook datang berkali-kali, tiket & email tidak dobel.
            if ($order->status === 'paid') {
                return;
            }

            // Simpan log setiap notifikasi untuk keperluan audit/debugging
            $order->payments()->create([
                'transaction_id' => $payload['transaction_id'] ?? null,
                'payment_type' => $payload['payment_type'] ?? null,
                'transaction_status' => $payload['transaction_status'],
                'raw_response' => $payload,
            ]);

            // Order yang sudah expired/failed juga tidak diubah lagi
            // (mencegah kuota dikembalikan dua kali)
            if ($order->status !== 'pending') {
                return;
            }

            $status = $payload['transaction_status'];
            $fraud = $payload['fraud_status'] ?? 'accept';

            if ($status === 'settlement' || ($status === 'capture' && $fraud === 'accept')) {
                $this->markAsPaid($order);
            } elseif ($status === 'expire') {
                $this->cancel($order, 'expired');
            } elseif (in_array($status, ['cancel', 'deny'])) {
                $this->cancel($order, 'failed');
            }
            // Status lain (mis. "pending") cukup dicatat di tabel payments
        });
    }

    /**
     * Tandai order lunas, terbitkan tiket (1 tiket per orang), lalu kirim email.
     */
    public function markAsPaid(Order $order): void
    {
        $order->update(['status' => 'paid', 'paid_at' => now()]);

        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->qty; $i++) {
                $item->tickets()->create(['code' => $this->generateTicketCode()]);
            }
        }

        // Masuk antrean (queue) dan baru dikirim SETELAH transaksi commit
        // (lihat afterCommit() di PaymentSuccessMail)
        Mail::to($order->user)->queue(new PaymentSuccessMail($order));
    }

    /**
     * Batalkan order (expired / failed) dan kembalikan kuota tiket.
     */
    public function cancel(Order $order, string $status, bool $sendEmail = true): void
    {
        DB::transaction(function () use ($order, $status, $sendEmail) {
            $order->update(['status' => $status]);
            $order->releaseQuota();

            if ($sendEmail) {
                Mail::to($order->user)->queue(new OrderExpiredMail($order));
            }
        });
    }

    /**
     * Cadangan jika webhook "expire" tidak pernah datang, misalnya customer
     * menutup popup Snap tanpa memilih metode pembayaran (Midtrans tidak
     * mencatat transaksi apa pun, sehingga tidak akan mengirim notifikasi).
     * Dipanggil oleh scheduler lewat command orders:expire.
     */
    public function expireOverdueOrders(): int
    {
        // Beri jeda 5 menit setelah expires_at agar notifikasi Midtrans
        // yang terlambat masih sempat diproses lebih dulu.
        $orderIds = Order::where('status', 'pending')
            ->where('expires_at', '<', now()->subMinutes(5))
            ->pluck('id');

        $count = 0;

        foreach ($orderIds as $id) {
            DB::transaction(function () use ($id, &$count) {
                $order = Order::lockForUpdate()->find($id);

                // Cek ulang: bisa saja webhook sudah mengubah statusnya
                if ($order->status === 'pending') {
                    $this->cancel($order, 'expired');
                    $count++;
                }
            });
        }

        return $count;
    }

    private function generateOrderCode(): string
    {
        // Contoh: INV-20261014-K7Q2XM
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    private function generateTicketCode(): string
    {
        // Contoh: TKT-9F2KD81QZA (inilah isi QR code)
        do {
            $code = 'TKT-'.Str::upper(Str::random(10));
        } while (Ticket::where('code', $code)->exists());

        return $code;
    }
}
