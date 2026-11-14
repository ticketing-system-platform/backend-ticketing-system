<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MidtransNotificationController;
use App\Http\Controllers\MyTicketController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

/*
| Semua route di file ini otomatis berawalan /api
*/

// ---------- Publik (tanpa login) ----------
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{slug}', [EventController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Webhook Midtrans: dipanggil server Midtrans, keamanannya lewat signature_key
Route::post('/midtrans/notification', MidtransNotificationController::class);

// ---------- Wajib login (token Sanctum) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Customer
    Route::post('/checkout', CheckoutController::class);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/my-tickets', MyTicketController::class);

    // Admin: prefix /api/admin + wajib role admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('events', Admin\EventController::class);
        Route::apiResource('ticket-types', Admin\TicketTypeController::class);
        Route::get('orders', [Admin\OrderController::class, 'index']);
        Route::post('checkin', Admin\CheckinController::class);
        Route::get('stats', Admin\StatsController::class);
    });
});
