<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
