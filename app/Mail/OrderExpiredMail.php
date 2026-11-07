<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    // Sama seperti PaymentSuccessMail: coba ulang jika koneksi SMTP terputus
    public $tries = 3;

    public $backoff = 10;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $label = $this->order->status === 'expired' ? 'Kedaluwarsa' : 'Gagal';

        return new Envelope(
            subject: "Order {$label} - {$this->order->order_code}",
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing('user');

        return new Content(
            markdown: 'mail.order-expired',
            with: [
                'eventsUrl' => config('app.frontend_url'),
            ],
        );
    }
}
