<x-mail::message>
# Pembayaran Berhasil 🎉

Halo **{{ $order->user->name }}**,

Terima kasih! Pembayaran untuk order **{{ $order->order_code }}** sudah kami terima.

<x-mail::table>
| Tiket | Qty | Subtotal |
|:------|:---:|---------:|
@foreach ($order->items as $item)
| {{ $item->ticketType->event->title }} - {{ $item->ticketType->name }} | {{ $item->qty }} | Rp {{ number_format($item->subtotal(), 0, ',', '.') }} |
@endforeach
| **Total** | | **Rp {{ number_format($order->total, 0, ',', '.') }}** |
</x-mail::table>

**Kode tiket Anda:**

@foreach ($order->items as $item)
@foreach ($item->tickets as $ticket)
- {{ $ticket->code }} ({{ $item->ticketType->name }})
@endforeach
@endforeach

Tunjukkan QR code di halaman **Tiket Saya** kepada petugas saat masuk ke lokasi acara.

<x-mail::button :url="$ticketsUrl">
Lihat Tiket Saya
</x-mail::button>

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
