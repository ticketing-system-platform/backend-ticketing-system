<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logika bisnis order: checkout, menerima notifikasi pembayaran,
 * menerbitkan tiket, dan membatalkan order.
 */
class OrderService
{
    /**
     * Buat order baru + tahan kuota tiket.
     *
     * @param  array<int, array{ticket_type_id: int, qty: int}>  $items
     */
    public function checkout(User $user, array $items): Order
    {
        return DB::transaction(function () use ($user, $items) {
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
                'expires_at' => now()->addMinutes(30),
            ]);
            $order->items()->createMany($lines);

            return $order;
        });
    }

    private function generateOrderCode(): string
    {
        // Contoh: INV-20261014-K7Q2XM
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
