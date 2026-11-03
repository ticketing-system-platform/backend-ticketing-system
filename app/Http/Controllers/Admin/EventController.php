<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::query()
            ->withSum('ticketTypes as tickets_sold', 'sold')
            ->withSum('ticketTypes as tickets_quota', 'quota')
            ->latest()
            ->get();

        return EventResource::collection($events);
    }

    public function store(EventRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['title']);

        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('banners', 'public');
        }

        $event = Event::create($data);

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    public function show(Event $event)
    {
        return new EventResource($event->load('ticketTypes'));
    }

    public function update(EventRequest $request, Event $event)
    {
        $data = $request->validated();

        if ($request->hasFile('banner')) {
            $this->deleteBanner($event);
            $data['banner'] = $request->file('banner')->store('banners', 'public');
        } else {
            unset($data['banner']);
        }

        $event->update($data);

        return new EventResource($event->load('ticketTypes'));
    }

    public function destroy(Event $event)
    {
        if ($event->ticketTypes()->whereHas('orderItems')->exists()) {
            return response()->json([
                'message' => 'Event sudah memiliki transaksi sehingga tidak bisa dihapus, ubah status menjadi draft',
            ], 422);
        }

        $this->deleteBanner($event);
        $event->delete();

        return response()->json(['message' => 'Event Berhasil dihapus']);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (Event::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function deleteBanner(Event $event): void
    {
        if ($event->banner && ! Str::startsWith($event->banner, 'http')) {
            Storage::disk('public')->delete($event->banner);
        }
    }
}
