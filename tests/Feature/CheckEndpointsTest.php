<?php

use App\Mail\OrderExpiredMail;
use App\Mail\PaymentSuccessMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// -------------------------------------------------------------
// 1. Auth Endpoints
// -------------------------------------------------------------

test('POST /api/register creates a customer and returns token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'John Doe')
        ->assertJsonPath('data.email', 'john@example.com')
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role'], 'token']);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'role' => 'customer',
    ]);
});

test('POST /api/login authenticates user and returns token', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.email', 'user@example.com')
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role'], 'token']);
});

test('POST /api/login fails with invalid credentials', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'user@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('POST /api/logout revokes token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Berhasil logout']);

    expect($user->tokens()->count())->toBe(0);
});

test('GET /api/me returns current user info', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'customer');
});

// -------------------------------------------------------------
// 2. Public Event Endpoints
// -------------------------------------------------------------

test('GET /api/events returns list of published events with search', function () {
    $event1 = Event::create([
        'title' => 'Rock Concert',
        'slug' => 'rock-concert',
        'description' => 'Rock music live',
        'venue' => 'Gelora Bung Karno',
        'start_at' => now()->addDays(10),
        'status' => 'published',
    ]);

    $event2 = Event::create([
        'title' => 'Jazz Festival',
        'slug' => 'jazz-festival',
        'description' => 'Jazz music event',
        'venue' => 'JIExpo',
        'start_at' => now()->addDays(20),
        'status' => 'published',
    ]);

    // Draft event should not be returned
    Event::create([
        'title' => 'Draft Festival',
        'slug' => 'draft-festival',
        'description' => 'Draft',
        'venue' => 'Unknown',
        'start_at' => now()->addDays(30),
        'status' => 'draft',
    ]);

    TicketType::create([
        'event_id' => $event1->id,
        'name' => 'VIP',
        'price' => 150000,
        'quota' => 100,
        'sold' => 10,
    ]);

    // Test regular list
    $response = $this->getJson('/api/events');
    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    // Test search by title
    $searchResponse = $this->getJson('/api/events?search=Rock');
    $searchResponse->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Rock Concert');

    // Test search by venue
    $searchResponse2 = $this->getJson('/api/events?search=JIExpo');
    $searchResponse2->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Jazz Festival');
});

test('GET /api/events/{slug} returns single published event or 404', function () {
    $event = Event::create([
        'title' => 'Indie Fest',
        'slug' => 'indie-fest',
        'description' => 'Indie music band',
        'venue' => 'Bandung',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $response = $this->getJson('/api/events/indie-fest');
    $response->assertStatus(200)
        ->assertJsonPath('data.slug', 'indie-fest');

    $notFound = $this->getJson('/api/events/non-existent-slug');
    $notFound->assertStatus(404);
});

// -------------------------------------------------------------
// 3. Admin Middleware Access Control
// -------------------------------------------------------------

test('Customer cannot access /api/admin/* endpoints', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $response = $this->actingAs($customer, 'sanctum')->getJson('/api/admin/events');
    $response->assertStatus(403);
});

test('Unauthenticated user cannot access /api/admin/* endpoints', function () {
    $response = $this->getJson('/api/admin/events');
    $response->assertStatus(401);
});

// -------------------------------------------------------------
// 4. Admin Event Endpoints
// -------------------------------------------------------------

test('GET /api/admin/events returns all events with quotas and sales', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $event = Event::create([
        'title' => 'Admin Event',
        'slug' => 'admin-event',
        'description' => 'Admin desc',
        'venue' => 'Jakarta',
        'start_at' => now()->addDays(3),
        'status' => 'draft',
    ]);

    TicketType::create([
        'event_id' => $event->id,
        'name' => 'Regular',
        'price' => 50000,
        'quota' => 100,
        'sold' => 25,
    ]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/events');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.tickets_sold', 25)
        ->assertJsonPath('data.0.tickets_quota', 100);
});

test('POST /api/admin/events creates an event with auto slug and banner upload', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);

    $file = UploadedFile::fake()->image('banner.jpg');

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/events', [
        'title' => 'My Super Event',
        'description' => 'Event description',
        'venue' => 'Senayan',
        'start_at' => '2026-12-01 19:00:00',
        'status' => 'published',
        'banner' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'My Super Event')
        ->assertJsonPath('data.slug', 'my-super-event');

    $this->assertDatabaseHas('events', [
        'title' => 'My Super Event',
        'slug' => 'my-super-event',
    ]);

    // Test unique slug collision handling
    $response2 = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/events', [
        'title' => 'My Super Event',
        'description' => 'Another event with same title',
        'venue' => 'Senayan 2',
        'start_at' => '2026-12-02 19:00:00',
        'status' => 'draft',
    ]);

    $response2->assertStatus(201)
        ->assertJsonPath('data.slug', 'my-super-event-2');
});

test('GET /api/admin/events/{event} returns event detail with ticketTypes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'title' => 'Detail Event',
        'slug' => 'detail-event',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $response = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/events/{$event->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $event->id)
        ->assertJsonPath('data.title', 'Detail Event');
});

test('PUT /api/admin/events/{event} updates event', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'title' => 'Old Title',
        'slug' => 'old-title',
        'description' => 'Old desc',
        'venue' => 'Old venue',
        'start_at' => now()->addDays(1),
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/events/{$event->id}", [
        'title' => 'New Title',
        'description' => 'New desc',
        'venue' => 'New venue',
        'start_at' => '2026-12-25 20:00:00',
        'status' => 'published',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'New Title')
        ->assertJsonPath('data.status', 'published');
});

test('DELETE /api/admin/events/{event} deletes event if no orders exist', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'title' => 'To Delete',
        'slug' => 'to-delete',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(1),
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/events/{$event->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Event Berhasil dihapus']);

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

// -------------------------------------------------------------
// 5. Admin TicketType Endpoints
// -------------------------------------------------------------

test('GET /api/admin/ticket-types returns list of ticket types with event', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'title' => 'Event A',
        'slug' => 'event-a',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    TicketType::create([
        'event_id' => $event->id,
        'name' => 'Economy',
        'price' => 50000,
        'quota' => 100,
        'sold' => 0,
    ]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/ticket-types');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Economy');

    $filteredResponse = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/ticket-types?event_id={$event->id}");
    $filteredResponse->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('POST /api/admin/ticket-types creates a new ticket type', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'title' => 'Event for Ticket',
        'slug' => 'event-for-ticket',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/ticket-types', [
        'event_id' => $event->id,
        'name' => 'Gold Pass',
        'price' => 200000,
        'quota' => 50,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Gold Pass')
        ->assertJsonPath('data.quota', 50)
        ->assertJsonPath('data.remaining', 50);

    $this->assertDatabaseHas('ticket_types', [
        'name' => 'Gold Pass',
        'event_id' => $event->id,
    ]);
});

test('GET /api/admin/ticket-types/{ticket_type} returns ticket type with event', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'title' => 'Event Detail TT',
        'slug' => 'event-detail-tt',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Platinum',
        'price' => 500000,
        'quota' => 30,
        'sold' => 5,
    ]);

    $response = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/ticket-types/{$ticketType->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.id', $ticketType->id)
        ->assertJsonPath('data.name', 'Platinum')
        ->assertJsonPath('data.remaining', 25)
        ->assertJsonPath('data.event.id', $event->id);
});

test('PUT /api/admin/ticket-types/{ticket_type} updates ticket type and validates quota', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'title' => 'Event Update TT',
        'slug' => 'event-update-tt',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Silver',
        'price' => 100000,
        'quota' => 20,
        'sold' => 10,
    ]);

    // Update with quota lower than sold should fail validation
    $invalidResponse = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/ticket-types/{$ticketType->id}", [
        'name' => 'Silver Updated',
        'price' => 120000,
        'quota' => 5, // sold is 10
    ]);

    $invalidResponse->assertStatus(422)
        ->assertJsonValidationErrors(['quota']);

    // Valid update
    $validResponse = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/ticket-types/{$ticketType->id}", [
        'name' => 'Silver Updated',
        'price' => 120000,
        'quota' => 25,
    ]);

    $validResponse->assertStatus(200)
        ->assertJsonPath('data.name', 'Silver Updated')
        ->assertJsonPath('data.quota', 25)
        ->assertJsonPath('data.remaining', 15);
});

test('DELETE /api/admin/ticket-types/{ticket_type} deletes ticket type if no orders', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'title' => 'Event Del TT',
        'slug' => 'event-del-tt',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Bronze',
        'price' => 50000,
        'quota' => 20,
        'sold' => 0,
    ]);

    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/ticket-types/{$ticketType->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'jenis tiket berhasil dihapus']);

    $this->assertDatabaseMissing('ticket_types', ['id' => $ticketType->id]);
});

test('DELETE /api/admin/events/{event} fails with 422 if orders exist', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();

    $event = Event::create([
        'title' => 'Event With Orders',
        'slug' => 'event-with-orders',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP',
        'price' => 100000,
        'quota' => 20,
        'sold' => 1,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_code' => 'ORD-001',
        'total' => 100000,
        'status' => 'paid',
        'expires_at' => now()->addHour(),
        'paid_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 100000,
    ]);

    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/events/{$event->id}");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Event sudah memiliki transaksi sehingga tidak bisa dihapus, ubah status menjadi draft');
});

test('DELETE /api/admin/ticket-types/{ticket_type} fails with 422 if orders exist', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();

    $event = Event::create([
        'title' => 'Event TT Orders',
        'slug' => 'event-tt-orders',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP',
        'price' => 100000,
        'quota' => 20,
        'sold' => 1,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_code' => 'ORD-002',
        'total' => 100000,
        'status' => 'paid',
        'expires_at' => now()->addHour(),
        'paid_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 100000,
    ]);

    $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/ticket-types/{$ticketType->id}");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Jenis tiket sudah pernah dipesan sehingga tidak bisa dihapus');
});

// -------------------------------------------------------------
// 5. Checkout & Order Endpoints (P5)
// -------------------------------------------------------------

test('POST /api/checkout creates order with pending status and increments sold', function () {
    $this->mock(\App\Services\MidtransService::class, function ($mock) {
        $mock->shouldReceive('createSnapToken')->once()->andReturn('mock-snap-token-123');
    });

    $user = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Checkout Event',
        'slug' => 'checkout-event',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Front Row',
        'price' => 150000,
        'quota' => 5,
        'sold' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'qty' => 3],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.snap_token', 'mock-snap-token-123')
        ->assertJsonPath('data.total', 450000)
        ->assertJsonPath('data.items.0.qty', 3)
        ->assertJsonPath('data.items.0.price', 150000)
        ->assertJsonPath('data.items.0.subtotal', 450000);

    expect($ticketType->fresh()->sold)->toBe(3);
    expect($ticketType->fresh()->remaining)->toBe(2);
});

test('POST /api/checkout releases quota and fails order if Midtrans fails', function () {
    $this->mock(\App\Services\MidtransService::class, function ($mock) {
        $mock->shouldReceive('createSnapToken')->once()->andThrow(new \Exception('Midtrans connection timeout'));
    });

    $user = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Checkout Event Fail',
        'slug' => 'checkout-event-fail',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP',
        'price' => 200000,
        'quota' => 10,
        'sold' => 2,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'qty' => 3],
        ],
    ]);

    $response->assertStatus(502);

    expect($ticketType->fresh()->sold)->toBe(2);
    $order = Order::latest('id')->first();
    expect($order->status)->toBe('failed');
});

test('POST /api/checkout fails with 422 if quota is exceeded', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Quota Event',
        'slug' => 'quota-event',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Front Row',
        'price' => 150000,
        'quota' => 5,
        'sold' => 3,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'qty' => 3],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    expect($ticketType->fresh()->sold)->toBe(3);
});

test('POST /api/checkout fails with 422 if duplicate ticket_type_id is provided', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Duplicate Item Event',
        'slug' => 'duplicate-item-event',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'General',
        'price' => 50000,
        'quota' => 20,
        'sold' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'qty' => 1],
            ['ticket_type_id' => $ticketType->id, 'qty' => 2],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.ticket_type_id', 'items.1.ticket_type_id']);
});

test('POST /api/checkout fails with 422 if event is not published', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Draft Event',
        'slug' => 'draft-event',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'draft',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Tiket Umum',
        'price' => 50000,
        'quota' => 20,
        'sold' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'qty' => 1],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

test('GET /api/orders returns user orders history', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Order List Event',
        'slug' => 'order-list-event',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Regular',
        'price' => 100000,
        'quota' => 50,
        'sold' => 2,
    ]);

    $order1 = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-USER-1',
        'total' => 100000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);
    OrderItem::create([
        'order_id' => $order1->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 100000,
    ]);

    // Order milik user lain
    $order2 = Order::create([
        'user_id' => $otherUser->id,
        'order_code' => 'INV-OTHER-1',
        'total' => 100000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/orders');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_code', 'INV-USER-1');
});

test('GET /api/orders/{order} returns order detail for owner and 404 for other customer', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);

    $order = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-DETAIL-1',
        'total' => 200000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);

    // Akses oleh pemilik: 200
    $response = $this->actingAs($user, 'sanctum')->getJson("/api/orders/{$order->order_code}");
    $response->assertStatus(200)
        ->assertJsonPath('data.order_code', 'INV-DETAIL-1');

    // Akses oleh user lain: 404
    $otherResponse = $this->actingAs($otherUser, 'sanctum')->getJson("/api/orders/{$order->order_code}");
    $otherResponse->assertStatus(404);
});

// -------------------------------------------------------------
// 6. Midtrans Webhook & Ticket Issuance (P7)
// -------------------------------------------------------------

test('POST /api/midtrans/notification rejects invalid signature with 403', function () {
    config(['midtrans.server_key' => 'SB-Mid-server-testkey']);

    $response = $this->postJson('/api/midtrans/notification', [
        'order_id' => 'INV-TEST-001',
        'status_code' => '200',
        'gross_amount' => '100000.00',
        'signature_key' => 'invalid-signature-hash',
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Signature tidak valid.']);
});

test('POST /api/midtrans/notification processes settlement and issues tickets and sends email', function () {
    Mail::fake();
    config(['midtrans.server_key' => 'SB-Mid-server-testkey']);

    $user = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Concert P7',
        'slug' => 'concert-p7',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP',
        'price' => 100000,
        'quota' => 20,
        'sold' => 2,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-P7-SUCCESS',
        'total' => 200000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 2,
        'price' => 100000,
    ]);

    $orderId = 'INV-P7-SUCCESS';
    $statusCode = '200';
    $grossAmount = '200000.00';
    $validSignature = hash('sha512', $orderId.$statusCode.$grossAmount.'SB-Mid-server-testkey');

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $validSignature,
        'transaction_id' => 'trx-12345',
        'payment_type' => 'bank_transfer',
        'transaction_status' => 'settlement',
    ];

    $response = $this->postJson('/api/midtrans/notification', $payload);

    $response->assertStatus(200)
        ->assertJson(['message' => 'OK']);

    $order->refresh();
    expect($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull();

    // Pastikan 2 tiket terbit untuk qty=2
    expect($order->tickets()->count())->toBe(2);
    expect($order->tickets()->first()->code)->toStartWith('TKT-');

    // Pastikan payment log tersimpan
    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'transaction_id' => 'trx-12345',
        'transaction_status' => 'settlement',
    ]);

    Mail::assertQueued(PaymentSuccessMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id;
    });

    // Idempoten: kirim ulang notifikasi settlement yang sama
    $secondResponse = $this->postJson('/api/midtrans/notification', $payload);
    $secondResponse->assertStatus(200);

    // Jumlah tiket dan email tidak boleh bertambah
    expect($order->fresh()->tickets()->count())->toBe(2);
    Mail::assertQueued(PaymentSuccessMail::class, 1);
});

test('POST /api/midtrans/notification processes expire, releases quota, and sends email', function () {
    Mail::fake();
    config(['midtrans.server_key' => 'SB-Mid-server-testkey']);

    $user = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Concert Expire',
        'slug' => 'concert-expire',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Regular',
        'price' => 50000,
        'quota' => 10,
        'sold' => 3,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-P7-EXPIRE',
        'total' => 150000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 3,
        'price' => 50000,
    ]);

    $orderId = 'INV-P7-EXPIRE';
    $statusCode = '202';
    $grossAmount = '150000.00';
    $validSignature = hash('sha512', $orderId.$statusCode.$grossAmount.'SB-Mid-server-testkey');

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $validSignature,
        'transaction_id' => 'trx-expired',
        'payment_type' => 'bank_transfer',
        'transaction_status' => 'expire',
    ];

    $response = $this->postJson('/api/midtrans/notification', $payload);
    $response->assertStatus(200);

    $order->refresh();
    expect($order->status)->toBe('expired');
    expect($ticketType->fresh()->sold)->toBe(0);

    Mail::assertQueued(OrderExpiredMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id;
    });
});

test('expireOverdueOrders cancels orders past expires_at + 5 minutes', function () {
    Mail::fake();

    $user = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Scheduler Event',
        'slug' => 'scheduler-event',
        'description' => 'Desc',
        'venue' => 'Venue',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Early Bird',
        'price' => 50000,
        'quota' => 10,
        'sold' => 2,
    ]);

    $overdueOrder = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-OVERDUE',
        'total' => 100000,
        'status' => 'pending',
        'expires_at' => now()->subMinutes(6),
    ]);

    OrderItem::create([
        'order_id' => $overdueOrder->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 2,
        'price' => 50000,
    ]);

    $recentOrder = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-RECENT',
        'total' => 50000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $service = app(OrderService::class);
    $expiredCount = $service->expireOverdueOrders();

    expect($expiredCount)->toBe(1);
    expect($overdueOrder->fresh()->status)->toBe('expired');
    expect($recentOrder->fresh()->status)->toBe('pending');
    expect($ticketType->fresh()->sold)->toBe(0);
});

// -------------------------------------------------------------
// 7. My Tickets & Expire Console Commands (P8)
// -------------------------------------------------------------

test('GET /api/my-tickets returns only paid tickets of current user', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'My Tickets Event',
        'slug' => 'my-tickets-event',
        'description' => 'Desc',
        'venue' => 'Gelora Bung Karno',
        'start_at' => now()->addDays(5),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP Gold',
        'price' => 250000,
        'quota' => 20,
        'sold' => 2,
    ]);

    // Order 1: Paid order milik user
    $paidOrder = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-USER-PAID',
        'total' => 250000,
        'status' => 'paid',
        'paid_at' => now(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $item1 = OrderItem::create([
        'order_id' => $paidOrder->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 250000,
    ]);
    $item1->tickets()->create([
        'code' => 'TKT-USER-VIP1',
    ]);

    // Order 2: Pending order milik user (tiket tidak boleh muncul sebelum lunas)
    $pendingOrder = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-USER-PENDING',
        'total' => 250000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);
    $item2 = OrderItem::create([
        'order_id' => $pendingOrder->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 250000,
    ]);
    $item2->tickets()->create([
        'code' => 'TKT-USER-PENDING1',
    ]);

    // Order 3: Paid order milik other user
    $otherOrder = Order::create([
        'user_id' => $otherUser->id,
        'order_code' => 'INV-OTHER-PAID',
        'total' => 250000,
        'status' => 'paid',
        'paid_at' => now(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $item3 = OrderItem::create([
        'order_id' => $otherOrder->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 250000,
    ]);
    $item3->tickets()->create([
        'code' => 'TKT-OTHER-VIP1',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/my-tickets');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'TKT-USER-VIP1')
        ->assertJsonPath('data.0.ticket_type', 'VIP Gold')
        ->assertJsonPath('data.0.order_code', 'INV-USER-PAID')
        ->assertJsonPath('data.0.holder_name', $user->name)
        ->assertJsonPath('data.0.event.title', 'My Tickets Event');
});

test('Artisan orders:expire command runs successfully', function () {
    $this->artisan('orders:expire')
        ->expectsOutputToContain('order ditandai expired.')
        ->assertExitCode(0);
});

test('Artisan midtrans:simulate command simulates webhook notification', function () {
    \Illuminate\Support\Facades\Http::fake([
        '*/api/midtrans/notification' => \Illuminate\Support\Facades\Http::response(['message' => 'OK'], 200),
    ]);

    $user = User::factory()->create(['role' => 'customer']);
    $order = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-SIMULATE-001',
        'total' => 150000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->artisan('midtrans:simulate', [
        'order_code' => $order->order_code,
        'status' => 'settlement',
    ])->assertExitCode(0);

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($order) {
        return $request['order_id'] === $order->order_code
            && $request['transaction_status'] === 'settlement'
            && !empty($request['signature_key']);
    });
});

test('Artisan user:password command updates user password and revokes tokens', function () {
    $user = User::factory()->create([
        'email' => 'change-pass@test.com',
        'password' => 'oldpassword123',
    ]);
    $user->createToken('test-token');

    expect($user->tokens()->count())->toBe(1);

    $this->artisan('user:password', ['email' => $user->email])
        ->expectsQuestion("Password baru untuk {$user->email}", 'newsecretpassword')
        ->expectsOutputToContain('Password berhasil diganti.')
        ->assertExitCode(0);

    $user->refresh();
    expect(\Illuminate\Support\Facades\Hash::check('newsecretpassword', $user->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(0);

    // Test non-existing user
    $this->artisan('user:password', ['email' => 'notfound@test.com'])
        ->expectsOutputToContain('Akun tidak ditemukan.')
        ->assertExitCode(1);
});

// -------------------------------------------------------------
// 8. Admin Checkin, Orders, & Stats Endpoints (P9)
// -------------------------------------------------------------

test('POST /api/admin/checkin performs ticket check-in with validation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);

    $event = Event::create([
        'title' => 'Festival P9',
        'slug' => 'festival-p9',
        'description' => 'Desc',
        'venue' => 'Stadion Utama',
        'start_at' => now()->addDays(2),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Festival Regular',
        'price' => 75000,
        'quota' => 50,
        'sold' => 2,
    ]);

    // Paid Order
    $paidOrder = Order::create([
        'user_id' => $customer->id,
        'order_code' => 'INV-CHECKIN-PAID',
        'total' => 75000,
        'status' => 'paid',
        'paid_at' => now(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $itemPaid = OrderItem::create([
        'order_id' => $paidOrder->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 75000,
    ]);
    $ticket = $itemPaid->tickets()->create(['code' => 'TKT-VALID-001']);

    // Unpaid Order
    $pendingOrder = Order::create([
        'user_id' => $customer->id,
        'order_code' => 'INV-CHECKIN-PENDING',
        'total' => 75000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);
    $itemPending = OrderItem::create([
        'order_id' => $pendingOrder->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 75000,
    ]);
    $itemPending->tickets()->create(['code' => 'TKT-UNPAID-002']);

    // 1. Customer cannot check in
    $this->actingAs($customer, 'sanctum')->postJson('/api/admin/checkin', ['code' => 'TKT-VALID-001'])
        ->assertStatus(403);

    // 2. Ticket not found
    $this->actingAs($admin, 'sanctum')->postJson('/api/admin/checkin', ['code' => 'TKT-NOTFOUND'])
        ->assertStatus(404)
        ->assertJson(['message' => 'Tiket tidak ditemukan.']);

    // 3. Unpaid order
    $this->actingAs($admin, 'sanctum')->postJson('/api/admin/checkin', ['code' => 'TKT-UNPAID-002'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Order tiket ini belum lunas.']);

    // 4. Successful check-in
    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/checkin', ['code' => 'TKT-VALID-001']);
    $response->assertStatus(200)
        ->assertJsonPath('data.code', 'TKT-VALID-001')
        ->assertJsonPath('message', 'Check-in berhasil.');

    expect($ticket->fresh()->checked_in_at)->not->toBeNull();

    // 5. Already checked in
    $repeatResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/checkin', ['code' => 'TKT-VALID-001']);
    $repeatResponse->assertStatus(422)
        ->assertJsonPath('data.code', 'TKT-VALID-001')
        ->assertJsonStructure(['message', 'data']);
});

test('GET /api/admin/orders lists paginated orders with status filter and search', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user1 = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    $user2 = User::factory()->create(['name' => 'Siti Rahma', 'email' => 'siti@example.com']);

    $event = Event::create([
        'title' => 'Expo P9',
        'slug' => 'expo-p9',
        'description' => 'Desc',
        'venue' => 'JCC Senayan',
        'start_at' => now()->addDays(3),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'Reguler',
        'price' => 50000,
        'quota' => 100,
        'sold' => 2,
    ]);

    $order1 = Order::create([
        'user_id' => $user1->id,
        'order_code' => 'INV-BUDI-100',
        'total' => 50000,
        'status' => 'paid',
        'paid_at' => now(),
        'expires_at' => now()->addMinutes(30),
    ]);
    OrderItem::create([
        'order_id' => $order1->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 50000,
    ]);

    $order2 = Order::create([
        'user_id' => $user2->id,
        'order_code' => 'INV-SITI-200',
        'total' => 50000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(30),
    ]);
    OrderItem::create([
        'order_id' => $order2->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 1,
        'price' => 50000,
    ]);

    // Filter status=paid
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/orders?status=paid')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_code', 'INV-BUDI-100');

    // Search keyword
    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/orders?search=budi')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.name', 'Budi Santoso');
});

test('GET /api/admin/stats returns summary metrics for dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();

    $event = Event::create([
        'title' => 'Stats Event',
        'slug' => 'stats-event',
        'description' => 'Desc',
        'venue' => 'Hall A',
        'start_at' => now()->addDays(1),
        'status' => 'published',
    ]);

    $ticketType = TicketType::create([
        'event_id' => $event->id,
        'name' => 'VIP',
        'price' => 100000,
        'quota' => 10,
        'sold' => 2,
    ]);

    $paidOrder = Order::create([
        'user_id' => $user->id,
        'order_code' => 'INV-STATS-PAID',
        'total' => 200000,
        'status' => 'paid',
        'paid_at' => now(),
        'expires_at' => now()->addMinutes(30),
    ]);
    $item = OrderItem::create([
        'order_id' => $paidOrder->id,
        'ticket_type_id' => $ticketType->id,
        'qty' => 2,
        'price' => 100000,
    ]);
    $item->tickets()->create(['code' => 'TKT-STATS-1', 'checked_in_at' => now()]);
    $item->tickets()->create(['code' => 'TKT-STATS-2']);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/stats');

    $response->assertStatus(200)
        ->assertJsonPath('data.revenue', 200000)
        ->assertJsonPath('data.paid_orders', 1)
        ->assertJsonPath('data.pending_orders', 0)
        ->assertJsonPath('data.tickets_issued', 2)
        ->assertJsonPath('data.tickets_checked_in', 1)
        ->assertJsonStructure(['data' => ['events', 'recent_orders']]);
});


