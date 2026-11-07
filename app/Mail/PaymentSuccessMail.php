<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    // Koneksi SMTP bisa terputus sesaat (mis. Mailtrap menutup koneksi yang lama menganggur).
    // Coba kirim ulang maksimal 3 kali dengan jeda 10 detik, jangan langsung gagal.
    public $tries = 3;

    public $backoff = 10;

    public function __construct(public Order $order)
    {
        // Email baru dimasukkan ke antrean setelah transaksi database commit.
        // Jika transaksi gagal (rollback), email tidak pernah terkirim.
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pembayaran Berhasil - {$this->order->order_code}",
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing('user', 'items.ticketType.event', 'items.tickets');

        return new Content(
            markdown: 'mail.payment-success',
            with: [
                'ticketsUrl' => config('app.frontend_url').'/my-tickets',
            ],
        );
    }
}
