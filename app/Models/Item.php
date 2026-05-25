<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'condition',
        'item_age',
        'type',
        'exchange_preference',
        'price',
        'location',
        'location_lat',
        'location_lng',
        'notes',
        'bill_url',
        'sold_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'location_lat' => 'float',
        'location_lng' => 'float',
        'sold_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class);
    }

    public function exchangeRequests(): HasMany
    {
        return $this->hasMany(ExchangeRequest::class);
    }

    public function isSold(): bool
    {
        return $this->sold_at !== null;
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('sold_at');
    }

    public function scopeSold(Builder $query): Builder
    {
        return $query->whereNotNull('sold_at');
    }

    /**
     * Idempotently mark this item as sold (only if not already sold).
     */
    public function markSold(?\DateTimeInterface $at = null): bool
    {
        if ($this->sold_at !== null) {
            return false;
        }
        $this->forceFill(['sold_at' => $at ?: now()])->save();
        return true;
    }
}
