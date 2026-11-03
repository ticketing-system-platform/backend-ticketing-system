<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::published()
            ->with('ticketTypes')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%");
                });
            })
            ->orderBy('start_at')
            ->get();

        return EventResource::collection($events);
    }

    public function show(string $slug)
    {
        $event = Event::published()
            ->with('ticketTypes')
            ->where('slug', $slug)
            ->firstOrFail(); // otomatis 404 juga tidak ditemukan

        return new EventResource($event);
    }
}
