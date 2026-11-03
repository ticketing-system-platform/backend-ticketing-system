<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'venue' => $this->venue,
            'start_at' => $this->start_at,
            'start-at' => $this->start_at,
            'banner' => $this->banner,
            'banner_url' => $this->bannerUrl(),
            'status' => $this->status,
            'min_price' => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes->min('price')),
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            'tickets_type' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            'tickets_sold' => $this->whenHas('tickets_sold', fn () => (int) $this->tickets_sold),
            'tickets_quota' => $this->whenHas('tickets_quota', fn () => (int) $this->tickets_quota),
        ];
    }
}
