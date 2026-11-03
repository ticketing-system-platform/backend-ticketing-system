<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'name', 'price', 'quota', 'sold'])]
class TicketType extends Model
{
    protected $attributes = [
        'sold' => 0,
    ];

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'price' => 'integer',
            'quota' => 'integer',
            'sold' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected function remaining(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, $this->quota - $this->sold),
        );
    }
}
