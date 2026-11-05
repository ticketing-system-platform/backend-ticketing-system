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
