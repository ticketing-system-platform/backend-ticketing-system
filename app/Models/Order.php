<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['user_id', 'order_code', 'total', 'status', 'snap_token', 'expires_at', 'paid_at'])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function releaseQuota(): void
    {
        foreach ($this->items as $item) {
            TicketType::whereKey($item->ticket_type_id)->decrement('sold', $item->qty);
        }
    }
}
