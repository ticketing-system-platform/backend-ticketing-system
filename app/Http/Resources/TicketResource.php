<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'checked_in_at' => $this->checked_in_at,
            // Data di bawah hanya muncul jika relasi orderItem ikut di-load,
            // yaitu di halaman "Tiket Saya" dan hasil scan check-in
            $this->mergeWhen($this->relationLoaded('orderItem'), fn () => [
                'ticket_type' => $this->orderItem->ticketType->name,
                'order_code' => $this->orderItem->order->order_code,
                'holder_name' => $this->orderItem->order->user->name,
                'event' => new EventResource($this->orderItem->ticketType->event),
            ]),
        ];
    }
}
