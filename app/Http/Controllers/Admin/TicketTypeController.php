<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\TicketType;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    public function index(Request $request)
    {
        $ticketTypes = TicketType::with('event')
            ->when($request->event_id, fn ($query, $event_id) => $query->where('event_id', $event_id))
            ->orderBy('price')
            ->get();

        return TicketTypeResource::collection($ticketTypes);
    }

    public function store(TicketTypeRequest $request)
    {
        $ticketType = TicketType::create($request->validated());

        return (new TicketTypeResource($ticketType))->response()->setStatusCode(201);
    }

    public function show(TicketType $ticketType)
    {
        return new TicketTypeResource($ticketType->load('event'));
    }

    public function update(TicketTypeRequest $request, TicketType $ticketType)
    {
        $ticketType->update($request->validated());

        return new TicketTypeResource($ticketType);
    }

    public function destroy(TicketType $ticketType)
    {
        if ($ticketType->orderItems()->exists()) {
            return response()->json([
                'message' => 'Jenis tiket sudah pernah dipesan sehingga tidak bisa dihapus',
            ], 422);
        }

        $ticketType->delete();

        return response()->json(['message' => 'jenis tiket berhasil dihapus']);
    }
}
