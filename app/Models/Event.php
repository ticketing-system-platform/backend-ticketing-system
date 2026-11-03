<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['title', 'slug', 'description', 'venue', 'start_at', 'banner', 'status'])]
class Event extends Model
{
    protected function casts()
    {
        return [
            'start_at' => 'datetime',
        ];
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function bannerUrl(): ?string
    {
        if (! $this->banner) {
            return null;
        }

        if (Str::startsWith($this->banner, 'http')) {
            return $this->banner;
        }

        return Storage::disk('public')->url($this->banner);
    }
}
