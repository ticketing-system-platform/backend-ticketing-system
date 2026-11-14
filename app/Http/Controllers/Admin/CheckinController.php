<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckinRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;

class CheckinController extends Controller
{
    // POST /api/admin/checkin { "code": "TKT-XXXXXXXXXX" }
    public function __invoke(CheckinRequest $request)
    {
        $ticket = Ticket::with('orderItem.order.user', 'orderItem.ticketType.event')
            ->where('code', $request->code)
            ->first();

        // 1. Tiket harus ada
        if (! $ticket) {
            return response()->json(['message' => 'Tiket tidak ditemukan.'], 404);
        }

        // 2. Order harus sudah dibayar
        if ($ticket->orderItem->order->status !== 'paid') {
            return response()->json(['message' => 'Order tiket ini belum lunas.'], 422);
        }

        // 3. Tiket belum pernah dipakai
        if ($ticket->checked_in_at) {
            return response()->json([
                'message' => 'Tiket sudah dipakai check-in pada '.$ticket->checked_in_at->format('d/m/Y H:i').'.',
                'data' => new TicketResource($ticket),
            ], 422);
        }

        $ticket->update(['checked_in_at' => now()]);

        return (new TicketResource($ticket))->additional(['message' => 'Check-in berhasil.']);
    }
}
