<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\Request;

class MyTicketController extends Controller
{
    // GET /api/my-tickets
    public function __invoke(Request $request)
    {
        $tickets = Ticket::with('orderItem.ticketType.event', 'orderItem.order.user')
            // whereHas: filter berdasarkan relasi (tiket milik user ini & order sudah lunas)
            ->whereHas('orderItem.order', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->where('status', 'paid');
            })
            ->latest()
            ->get();

        return TicketResource::collection($tickets);
    }
}
