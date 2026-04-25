<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    protected $fillable = [
        'exchange_request_id',
        'sender_address',
        'receiver_address',
        'status',
        'provider',
        'awb_number',
        'tracking_url',
        'label_url',
        'status_code',
        'status_label',
        'estimated_delivery_at',
        'pickup_scheduled_at',
        'meta',
    ];

    protected $casts = [
        'estimated_delivery_at' => 'datetime',
        'pickup_scheduled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function exchangeRequest(): BelongsTo
    {
        return $this->belongsTo(ExchangeRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }
}
