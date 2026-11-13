<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * KHUSUS DEVELOPMENT.
 * Meniru notifikasi Midtrans ke webhook lokal, lengkap dengan signature_key yang valid.
 * Berguna saat ngrok tidak bisa dipakai (mis. jaringan kelas memblokir).
 *
 * Contoh: php artisan midtrans:simulate INV-20261014-ABC123 settlement
 */
#[Signature('midtrans:simulate {order_code} {status=settlement : settlement|pending|expire|cancel|deny}')]
#[Description('[DEV] Kirim notifikasi Midtrans palsu (bertanda tangan) ke webhook lokal')]
class SimulateMidtransNotification extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Command ini hanya untuk development.');

            return self::FAILURE;
        }

        $order = Order::where('order_code', $this->argument('order_code'))->first();

        if (! $order) {
            $this->error('Order tidak ditemukan.');

            return self::FAILURE;
        }

        $status = $this->argument('status');
        $statusCode = in_array($status, ['settlement', 'capture']) ? '200' : ($status === 'pending' ? '201' : '202');
        // Midtrans mengirim gross_amount sebagai string dengan 2 desimal
        $grossAmount = number_format($order->total, 2, '.', '');

        $payload = [
            'transaction_status' => $status,
            'status_code' => $statusCode,
            'order_id' => $order->order_code,
            'gross_amount' => $grossAmount,
            'payment_type' => 'bank_transfer',
            'transaction_id' => (string) Str::uuid(),
            'fraud_status' => 'accept',
            'signature_key' => hash('sha512', $order->order_code.$statusCode.$grossAmount.config('midtrans.server_key')),
        ];

        // Kirim ke endpoint webhook yang sama dengan yang dipanggil Midtrans
        $response = Http::post(url('/api/midtrans/notification'), $payload);

        $this->info("Webhook membalas: {$response->status()} {$response->body()}");
        $this->info('Status order sekarang: '.$order->fresh()->status);

        return self::SUCCESS;
    }
}
