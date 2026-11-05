<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qty' => $this->qty,
            'price' => $this->price,
            'subtotal' => $this->subtotal(),
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
