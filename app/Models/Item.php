<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'location_lat' => 'float',
        'location_lng' => 'float',
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
}
