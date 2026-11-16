# P2 — Auth, Role, CRUD API Event & Jenis Tiket

**Tujuan:** membuat API register/login dengan token, membatasi route admin, dan CRUD event & jenis tiket.

## 2.1 Pesan validasi Bahasa Indonesia

Laravel tidak menyertakan terjemahan Bahasa Indonesia. Kita buat sendiri untuk aturan validasi yang dipakai saja:

📄 `backend/lang/id/validation.php`

```php
<?php

// Pesan validasi Bahasa Indonesia (hanya aturan yang dipakai di proyek ini).
// Aturan lain otomatis memakai pesan bahasa Inggris (fallback_locale).
return [
    'array' => ':attribute harus berupa daftar.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute bukan tanggal yang valid.',
    'distinct' => ':attribute tidak boleh duplikat.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa angka bulat.',
    'max' => [
        'array' => ':attribute maksimal berisi :max item.',
        'file' => ':attribute maksimal :max kilobyte.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],
    'min' => [
        'array' => ':attribute minimal berisi :min item.',
        'file' => ':attribute minimal :min kilobyte.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah terdaftar.',

    // Nama field yang tampil di pesan error
    'attributes' => [
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Password',
        'title' => 'Judul',
        'description' => 'Deskripsi',
        'venue' => 'Lokasi',
        'start_at' => 'Waktu mulai',
        'status' => 'Status',
        'banner' => 'Banner',
        'event_id' => 'Event',
        'price' => 'Harga',
        'quota' => 'Kuota',
        'items' => 'Tiket',
        'items.*.ticket_type_id' => 'Jenis tiket',
        'items.*.qty' => 'Jumlah tiket',
        'code' => 'Kode tiket',
    ],
];
```

📄 `backend/lang/id.json`

```json
{
    "(and :count more error)": "(dan :count kesalahan lainnya)",
    "(and :count more errors)": "(dan :count kesalahan lainnya)"
}
```

💡 **Penjelasan:** dengan `APP_LOCALE=id`, Laravel membaca `lang/id/validation.php`. Aturan yang tidak ada di file ini
otomatis memakai bahasa Inggris (`APP_FALLBACK_LOCALE=en`). `attributes` mengganti nama field, sehingga pesan
`The title field is required` menjadi `Judul wajib diisi.`

## 2.2 Middleware role & konfigurasi aplikasi

```bash
php artisan make:middleware RoleMiddleware
```

📄 `backend/app/Http/Middleware/RoleMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Pastikan user yang login memiliki role tertentu.
     * Pemakaian di route: ->middleware('role:admin')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role !== $role) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke halaman ini.',
            ], 403);
        }

        return $next($request);
    }
}
```

Daftarkan alias middleware di file bootstrap aplikasi:

📄 `backend/bootstrap/app.php`

```php
<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Daftarkan alias agar bisa dipakai di route: ->middleware('role:admin')
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // Halaman login ada di React, bukan di Laravel. Jadi request tanpa token
        // cukup dibalas 401 (JSON), jangan di-redirect ke route('login').
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error di route /api/* selalu dikembalikan dalam format JSON
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
```

💡 **Penjelasan:**
- Parameter setelah titik dua (`role:admin`) diterima sebagai argumen `$role`.
- `redirectGuestsTo(fn () => null)`: tanpa ini, request tanpa token dari Postman (tanpa header `Accept: application/json`)
  akan error **"Route [login] not defined"** karena Laravel mencoba redirect ke halaman login yang tidak ada.
- `shouldRenderJsonWhen` memastikan semua error di `/api/*` berbentuk JSON, bukan halaman HTML.

## 2.3 Form Request (validasi)

```bash
php artisan make:request Auth/RegisterRequest
php artisan make:request Auth/LoginRequest
php artisan make:request Admin/EventRequest
php artisan make:request Admin/TicketTypeRequest
```

📄 `backend/app/Http/Requests/Auth/RegisterRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // confirmed: wajib ada field password_confirmation yang nilainya sama
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

📄 `backend/app/Http/Requests/Auth/LoginRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

📄 `backend/app/Http/Requests/Admin/EventRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

// Dipakai untuk tambah (store) dan ubah (update) event
class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // pengecekan role sudah dilakukan middleware role:admin
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'venue' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'status' => ['required', 'in:draft,published'],
            // banner opsional: maksimal 2 MB, harus berupa gambar
            'banner' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
```

📄 `backend/app/Http/Requests/Admin/TicketTypeRequest.php`

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0'],
            'quota' => ['required', 'integer', 'min:1'],
        ];

        // event_id hanya diisi saat membuat jenis tiket baru (POST).
        // Saat update, jenis tiket tidak bisa dipindah ke event lain.
        if ($this->isMethod('post')) {
            $rules['event_id'] = ['required', 'exists:events,id'];
        }

        return $rules;
    }

    /**
     * Validasi tambahan setelah rules di atas:
     * kuota tidak boleh lebih kecil dari tiket yang sudah terjual.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $ticketType = $this->route('ticket_type'); // null saat POST

                if ($ticketType && (int) $this->input('quota') < $ticketType->sold) {
                    $validator->errors()->add(
                        'quota',
                        "Kuota tidak boleh kurang dari tiket yang sudah terjual ({$ticketType->sold})."
                    );
                }
            },
        ];
    }
}
```

💡 **Penjelasan:**
- **Form Request** memindahkan validasi keluar dari controller. Jika validasi gagal, Laravel otomatis membalas
  **422** berisi `{ message, errors }`. Controller cukup memanggil `$request->validated()`.
- `authorize()` mengembalikan `true` karena pengecekan role sudah dilakukan middleware.
- `after()` di `TicketTypeRequest` adalah validasi tambahan yang butuh data database: kuota tidak boleh diturunkan
  di bawah jumlah tiket yang sudah terjual.

## 2.4 API Resource (format JSON)

```bash
php artisan make:resource UserResource
php artisan make:resource EventResource
php artisan make:resource TicketTypeResource
```

📄 `backend/app/Http/Resources/UserResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
```

📄 `backend/app/Http/Resources/EventResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'venue' => $this->venue,
            'start_at' => $this->start_at,
            'banner_url' => $this->bannerUrl(),
            'status' => $this->status,
            // whenLoaded: hanya ditampilkan jika relasi sudah di-load (with / load)
            'min_price' => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes->min('price')),
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            // whenHas: hanya ada jika query memakai withSum(...)
            'tickets_sold' => $this->whenHas('tickets_sold', fn () => (int) $this->tickets_sold),
            'tickets_quota' => $this->whenHas('tickets_quota', fn () => (int) $this->tickets_quota),
        ];
    }
}
```

📄 `backend/app/Http/Resources/TicketTypeResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'price' => $this->price,
            'quota' => $this->quota,
            'sold' => $this->sold,
            'remaining' => $this->remaining(),
            'event' => new EventResource($this->whenLoaded('event')),
        ];
    }
}
```

💡 **Penjelasan:**
- **API Resource** menentukan bentuk JSON yang dikirim, sehingga kolom sensitif (mis. `password`) tidak bocor
  dan format response konsisten (selalu dibungkus `data`).
- `whenLoaded('ticketTypes')`: relasi hanya ditampilkan jika sudah di-load dengan `with()`. Ini mencegah query N+1 tanpa sengaja.
- `whenHas('tickets_sold')`: kolom hasil `withSum()` hanya ditampilkan jika memang ada di query.

## 2.5 Controller auth

```bash
php artisan make:controller AuthController
```

📄 `backend/app/Http/Controllers/AuthController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/register
    public function register(RegisterRequest $request)
    {
        // Pendaftar baru selalu menjadi customer
        $user = User::create([...$request->validated(), 'role' => 'customer']);

        $token = $user->createToken('auth')->plainTextToken;

        return (new UserResource($user))
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(201);
    }

    // POST /api/login
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return (new UserResource($user))->additional(['token' => $token]);
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        // Hapus token yang sedang dipakai saja (perangkat lain tetap login)
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    // GET /api/me
    public function me(Request $request)
    {
        return new UserResource($request->user());
    }
}
```

💡 **Penjelasan:**
- `createToken('auth')->plainTextToken` menghasilkan token seperti `1|AbCd...`. React menyimpannya lalu mengirimnya di header
  `Authorization: Bearer <token>`.
- `additional(['token' => ...])` menambahkan field di luar `data`, hasilnya `{ "data": {...user}, "token": "..." }`.
- Pesan login salah sengaja dibuat umum ("Email atau password salah") agar penyerang tidak tahu email mana yang terdaftar.

## 2.6 Controller event (publik & admin)

```bash
php artisan make:controller EventController
php artisan make:controller Admin/EventController
php artisan make:controller Admin/TicketTypeController
```

📄 `backend/app/Http/Controllers/EventController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

// Endpoint publik (tanpa login) untuk halaman Home & Detail Event
class EventController extends Controller
{
    // GET /api/events?search=musik
    public function index(Request $request)
    {
        $events = Event::published()
            ->with('ticketTypes')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%");
                });
            })
            ->orderBy('start_at')
            ->get();

        return EventResource::collection($events);
    }

    // GET /api/events/{slug}
    public function show(string $slug)
    {
        $event = Event::published()
            ->with('ticketTypes')
            ->where('slug', $slug)
            ->firstOrFail(); // otomatis 404 jika tidak ditemukan

        return new EventResource($event);
    }
}
```

📄 `backend/app/Http/Controllers/Admin/EventController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventController extends Controller
{
    // GET /api/admin/events
    public function index()
    {
        $events = Event::query()
            // withSum: hitung total kolom di relasi tanpa me-load semua datanya
            ->withSum('ticketTypes as tickets_sold', 'sold')
            ->withSum('ticketTypes as tickets_quota', 'quota')
            ->latest()
            ->get();

        return EventResource::collection($events);
    }

    // POST /api/admin/events (multipart/form-data karena ada upload banner)
    public function store(EventRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['title']);

        if ($request->hasFile('banner')) {
            // Simpan ke storage/app/public/banners, hasilnya path: banners/xxxx.jpg
            $data['banner'] = $request->file('banner')->store('banners', 'public');
        }

        $event = Event::create($data);

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    // GET /api/admin/events/{id}
    public function show(Event $event)
    {
        return new EventResource($event->load('ticketTypes'));
    }

    // PUT /api/admin/events/{id}
    // Catatan: upload file + PUT tidak didukung PHP, jadi dari React kirim
    // POST dengan field _method=PUT (method spoofing Laravel).
    public function update(EventRequest $request, Event $event)
    {
        $data = $request->validated();

        if ($request->hasFile('banner')) {
            $this->deleteBanner($event);
            $data['banner'] = $request->file('banner')->store('banners', 'public');
        } else {
            unset($data['banner']); // banner lama tetap dipakai
        }

        // Slug sengaja tidak diubah agar link event yang sudah dibagikan tetap berfungsi
        $event->update($data);

        return new EventResource($event->load('ticketTypes'));
    }

    // DELETE /api/admin/events/{id}
    public function destroy(Event $event)
    {
        if ($event->ticketTypes()->whereHas('orderItems')->exists()) {
            return response()->json([
                'message' => 'Event sudah memiliki transaksi sehingga tidak bisa dihapus. Ubah status menjadi draft.',
            ], 422);
        }

        $this->deleteBanner($event);
        $event->delete(); // ticket_types ikut terhapus (cascadeOnDelete)

        return response()->json(['message' => 'Event berhasil dihapus.']);
    }

    // "Konser Musik" -> "konser-musik", jika sudah dipakai -> "konser-musik-2"
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (Event::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function deleteBanner(Event $event): void
    {
        // Banner dari seeder berupa URL internet, tidak perlu dihapus
        if ($event->banner && ! Str::startsWith($event->banner, 'http')) {
            Storage::disk('public')->delete($event->banner);
        }
    }
}
```

📄 `backend/app/Http/Controllers/Admin/TicketTypeController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\TicketType;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    // GET /api/admin/ticket-types?event_id=1
    public function index(Request $request)
    {
        $ticketTypes = TicketType::with('event')
            ->when($request->event_id, fn ($query, $eventId) => $query->where('event_id', $eventId))
            ->orderBy('price')
            ->get();

        return TicketTypeResource::collection($ticketTypes);
    }

    // POST /api/admin/ticket-types
    public function store(TicketTypeRequest $request)
    {
        $ticketType = TicketType::create($request->validated());

        return (new TicketTypeResource($ticketType))->response()->setStatusCode(201);
    }

    // GET /api/admin/ticket-types/{id}
    public function show(TicketType $ticketType)
    {
        return new TicketTypeResource($ticketType->load('event'));
    }

    // PUT /api/admin/ticket-types/{id}
    public function update(TicketTypeRequest $request, TicketType $ticketType)
    {
        $ticketType->update($request->validated());

        return new TicketTypeResource($ticketType);
    }

    // DELETE /api/admin/ticket-types/{id}
    public function destroy(TicketType $ticketType)
    {
        if ($ticketType->orderItems()->exists()) {
            return response()->json([
                'message' => 'Jenis tiket sudah pernah dipesan sehingga tidak bisa dihapus.',
            ], 422);
        }

        $ticketType->delete();

        return response()->json(['message' => 'Jenis tiket berhasil dihapus.']);
    }
}
```

💡 **Penjelasan:**
- Endpoint publik memakai `Event::published()` sehingga event draft tidak bisa diakses dari luar (404).
- Upload banner: `$request->file('banner')->store('banners', 'public')` menyimpan ke `storage/app/public/banners`
  dan mengembalikan path-nya, misalnya `banners/abc.jpg`.
- **Method spoofing**: PHP hanya membaca file upload dari request `POST`. Untuk update dengan file, React mengirim
  `POST` + field `_method=PUT`, lalu Laravel memperlakukannya sebagai `PUT`.
- Slug dibuat otomatis dan dijaga unik (`konser-musik`, `konser-musik-2`, dst.).
- Hapus event/jenis tiket ditolak (422) jika sudah ada transaksi, demi menjaga riwayat.

## 2.7 Route API (versi P2)

📄 `backend/routes/api.php`

```php
<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

/*
| Semua route di file ini otomatis berawalan /api
*/

// ---------- Publik (tanpa login) ----------
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{slug}', [EventController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ---------- Wajib login (token Sanctum) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Admin: prefix /api/admin + wajib role admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('events', Admin\EventController::class);
        Route::apiResource('ticket-types', Admin\TicketTypeController::class);
    });
});
```

Aktifkan link storage (agar banner bisa diakses lewat URL), lalu jalankan server:

```bash
php artisan storage:link
php artisan route:list --path=api
php artisan serve
```

💡 **Penjelasan:**
- `apiResource` otomatis membuat 5 route: `index`, `store`, `show`, `update`, `destroy`.
- `use App\Http\Controllers\Admin;` mengimpor *namespace*, sehingga kita bisa menulis `Admin\EventController::class`.
- `storage:link` membuat shortcut `public/storage` → `storage/app/public`.

## 2.8 Uji dengan Postman

1. Import 📄 `docs/tiket-event.postman_collection.json` ke Postman (**Import → File**).
2. Jalankan **1. Auth → Login Customer** dan **Login Admin**. Token tersimpan otomatis ke *Collection Variables*.
3. Coba **2. Publik** dan sebagian request di **6. Admin** (Daftar Event, Tambah Event, Jenis Tiket).

Jika ingin mengetik manual, gunakan header `Accept: application/json` dan `Authorization: Bearer <token>`.

| Uji | Hasil yang diharapkan |
|---|---|
| `GET /api/me` tanpa token | 401 `Unauthenticated.` |
| `GET /api/admin/events` dengan token customer | 403 `Anda tidak memiliki akses ke halaman ini.` |
| `POST /api/register` dengan body kosong | 422 + pesan Bahasa Indonesia |
| `GET /api/events/pameran-seni-rupa-nusantara` | 404 (event draft) |

## 🧑‍🏫 Catatan Mengajar P2

- Jelaskan perbedaan **401** (belum login) dan **403** (sudah login tapi tidak berhak).
- Bandingkan isi JSON **dengan** dan **tanpa** API Resource. Tunjukkan bahwa tanpa Resource, kolom tak penting ikut terkirim.
- Demo upload banner di Postman: **Body → form-data**, ubah tipe field `banner` menjadi **File**.

## ✅ Checkpoint P2

- [ ] Register & login mengembalikan token
- [ ] Route admin menolak customer (403)
- [ ] CRUD event (dengan banner) dan jenis tiket berjalan di Postman

---

⬅️ [P1 — ERD, Migration, Seeder](P01-erd-migration-seeder.md) · 🏠 [Daftar isi](../../README.md#daftar-isi) · [P3 — React: Setup, Layout, Auth, Daftar & Detail Event](P03-react-daftar-detail-event.md) ➡️
