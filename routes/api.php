<?php

use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\TicketTypeController as AdminTicketTypeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MidtransNotificationController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Publik tanpa login
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{slug}', [EventController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Webhook Midtrans: dipanggil server Midtrans, keamanannya lewat signature_key
Route::post('/midtrans/notification', MidtransNotificationController::class);

// Wajib login
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Customer
    Route::post('/checkout', CheckoutController::class);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // Admin: prefix /api/admin + wajib role admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::apiResource('events', AdminEventController::class);
        Route::apiResource('ticket-types', AdminTicketTypeController::class);
    });
});
