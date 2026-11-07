<x-mail::message>
# Order {{ $order->status === 'expired' ? 'Kedaluwarsa' : 'Gagal' }}

Halo **{{ $order->user->name }}**,

@if ($order->status === 'expired')
Order **{{ $order->order_code }}** tidak dibayar sampai batas waktu, sehingga order dibatalkan otomatis.
@else
Pembayaran untuk order **{{ $order->order_code }}** dibatalkan atau ditolak.
@endif

Tiket yang sempat dipesan sudah dikembalikan ke kuota. Jika masih ingin hadir, silakan pesan ulang.

<x-mail::button :url="$eventsUrl">
Lihat Event
</x-mail::button>

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
