<?php

use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\TicketTypeController as AdminTicketTypeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

// Publik tanpa login
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{slug}', [EventController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Wajib login
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Admin: prefix /api/admin + wajib role admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::apiResource('events', AdminEventController::class);
        Route::apiResource('ticket-types', AdminTicketTypeController::class);
    });
});
