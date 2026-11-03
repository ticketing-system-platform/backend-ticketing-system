<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'price' => $this->price,
            'quota' => $this->quota,
            'sold' => $this->sold,
            'remaining' => $this->remaining,
            'event' => new EventResource($this->whenLoaded('event')),
        ];
    }
}
