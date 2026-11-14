<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\OrderResource;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;

class StatsController extends Controller
{
    // GET /api/admin/stats — ringkasan untuk halaman Dashboard
    public function __invoke()
    {
        $events = Event::withSum('ticketTypes as tickets_sold', 'sold')
            ->withSum('ticketTypes as tickets_quota', 'quota')
            ->orderBy('start_at')
            ->get();

        $recentOrders = Order::with('user')->latest()->take(5)->get();

        return response()->json([
            'data' => [
                'revenue' => (int) Order::where('status', 'paid')->sum('total'),
                'paid_orders' => Order::where('status', 'paid')->count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'tickets_issued' => Ticket::count(),
                'tickets_checked_in' => Ticket::whereNotNull('checked_in_at')->count(),
                'events' => EventResource::collection($events),
                'recent_orders' => OrderResource::collection($recentOrders),
            ],
        ]);
    }
}
