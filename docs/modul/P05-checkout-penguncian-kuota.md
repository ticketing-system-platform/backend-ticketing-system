# P5 — Checkout + Penguncian Kuota

**Tujuan:** membuat endpoint checkout yang **aman dari overselling**, yaitu tiket terjual melebihi kuota saat banyak orang membeli bersamaan.

## 5.1 Masalah race condition

Bayangkan sisa tiket Front Row tinggal **1**, lalu dua orang menekan "Bayar" di detik yang sama:

| Waktu | Request A | Request B |
|---|---|---|
| t1 | baca sisa = 1 ✅ | |
| t2 | | baca sisa = 1 ✅ |
| t3 | sold + 1 | |
| t4 | | sold + 1 → **terjual 2, padahal kuota 1** ❌ |

**Solusi:** `DB::transaction` + `lockForUpdate()`. Baris `ticket_types` dikunci, sehingga request B harus **menunggu**
sampai request A selesai, lalu membaca sisa kuota yang sudah diperbarui (0).

## 5.2 Request, Resource, Service

```bash
php artisan make:request CheckoutRequest
php artisan make:resource OrderResource
php artisan make:resource OrderItemResource
php artisan make:resource TicketResource
```

📄 `backend/app/Http/Requests/CheckoutRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Contoh body:
     * { "items": [ { "ticket_type_id": 1, "qty": 2 }, { "ticket_type_id": 2, "qty": 1 } ] }
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            // distinct: jenis tiket yang sama tidak boleh muncul dua kali
            'items.*.ticket_type_id' => ['required', 'integer', 'distinct', 'exists:ticket_types,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
```

📄 `backend/app/Http/Resources/OrderResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'total' => $this->total,
            'status' => $this->status,
            // snap_token hanya berguna selama order masih menunggu pembayaran
            'snap_token' => $this->when($this->status === 'pending', $this->snap_token),
            'expires_at' => $this->expires_at,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
```

📄 `backend/app/Http/Resources/OrderItemResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qty' => $this->qty,
            'price' => $this->price,
            'subtotal' => $this->subtotal(),
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
```

📄 `backend/app/Http/Resources/TicketResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'checked_in_at' => $this->checked_in_at,
            // Data di bawah hanya muncul jika relasi orderItem ikut di-load,
            // yaitu di halaman "Tiket Saya" dan hasil scan check-in
            $this->mergeWhen($this->relationLoaded('orderItem'), fn () => [
                'ticket_type' => $this->orderItem->ticketType->name,
                'order_code' => $this->orderItem->order->order_code,
                'holder_name' => $this->orderItem->order->user->name,
                'event' => new EventResource($this->orderItem->ticketType->event),
            ]),
        ];
    }
}
```

> `TicketResource` dibuat sekarang karena sudah dirujuk oleh `OrderItemResource`. Datanya baru terisi setelah P7.

Buat folder `app/Services` dan file 📄 `backend/app/Services/OrderService.php` (**versi P5**):

```php
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
```

💡 **Penjelasan:**
- **Service class** memisahkan logika bisnis dari controller, sehingga controller tetap tipis dan logika mudah dites/dipakai ulang.
- Jika terjadi exception di dalam `DB::transaction`, **semua perubahan dibatalkan (rollback)**, termasuk `sold` yang sudah bertambah.
- `ValidationException::withMessages` menghasilkan response **422** yang formatnya sama dengan validasi biasa.
- Mengunci baris dengan **urutan id yang sama** mencegah *deadlock* (A mengunci 1 lalu menunggu 2, sementara B mengunci 2 lalu menunggu 1).

## 5.3 Controller & route

```bash
php artisan make:controller CheckoutController --invokable
php artisan make:controller OrderController
```

📄 `backend/app/Http/Controllers/CheckoutController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;

class CheckoutController extends Controller
{
    // POST /api/checkout
    // Controller hanya "mengantar": validasi oleh Form Request,
    // logika bisnis oleh OrderService, format response oleh API Resource.
    public function __invoke(CheckoutRequest $request, OrderService $orders)
    {
        $order = $orders->checkout($request->user(), $request->validated('items'));

        $order->load('items.ticketType.event');

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}
```

📄 `backend/app/Http/Controllers/OrderController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

// Riwayat order milik customer yang sedang login
class OrderController extends Controller
{
    // GET /api/orders
    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with('items.ticketType.event')
            ->latest()
            ->get();

        return OrderResource::collection($orders);
    }

    // GET /api/orders/{order_code}
    public function show(Request $request, Order $order)
    {
        // Customer hanya boleh melihat order miliknya sendiri
        abort_if($order->user_id !== $request->user()->id, 404, 'Order tidak ditemukan.');

        $order->load('items.ticketType.event', 'items.tickets');

        return new OrderResource($order);
    }
}
```

Tambahkan di 📄 `backend/routes/api.php`. Baris `use` diletakkan di bagian atas file bersama `use` lainnya,
sedangkan baris route di dalam grup `auth:sanctum` (sebelum grup admin):

```php
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;

    // Customer
    Route::post('/checkout', CheckoutController::class);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
```

💡 **Penjelasan:**
- **Invokable controller** (`__invoke`) cocok untuk controller dengan satu aksi. Route cukup menulis `CheckoutController::class`.
- `OrderService $orders` di parameter method otomatis dibuatkan Laravel (**dependency injection**).
- `abort_if(... , 404)` mencegah customer melihat order milik orang lain. Kita membalas 404, bukan 403, agar tidak membocorkan bahwa order itu ada.

## 5.4 Uji di Postman

Body `POST /api/checkout` (token customer):

```json
{ "items": [ { "ticket_type_id": 7, "qty": 3 } ] }
```

| Uji | Hasil |
|---|---|
| Checkout 3 tiket Front Row (kuota 5) | 201, `sold` menjadi 3 |
| Checkout lagi 3 tiket Front Row | 422 `Sisa kuota tiket Front Row tinggal 2.` |
| `ticket_type_id` sama dua kali | 422 `Jenis tiket tidak boleh duplikat. (dan 1 kesalahan lainnya)` |
| Tiket milik event draft (id 9) | 422 `Tiket Umum belum dijual.` |

## 🧑‍🏫 Catatan Mengajar P5

- Peragakan race condition dengan dua siswa "berebut" tiket terakhir di papan tulis sebelum menunjukkan kodenya.
- Tunjukkan bahwa setelah request gagal 422, nilai `sold` di database **tidak berubah** (efek rollback).
- Pertanyaan diskusi: "Kenapa kuota ditahan saat checkout, bukan saat bayar?" (agar customer yang sedang membayar tidak kehabisan tiket).

## ✅ Checkpoint P5

- [ ] Checkout membuat order `pending` + `order_items`, dan `sold` bertambah
- [ ] Kuota tidak bisa terlampaui
- [ ] `GET /api/orders` dan `GET /api/orders/{code}` berfungsi

---

⬅️ [P4 — Halaman Admin: CRUD Event & Jenis Tiket](P04-halaman-admin.md) · 🏠 [Daftar isi](../../README.md#daftar-isi) · [P6 — Snap Token + Popup Pembayaran](P06-snap-popup-pembayaran.md) ➡️
