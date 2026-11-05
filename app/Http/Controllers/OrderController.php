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
