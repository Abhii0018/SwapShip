<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExchangeRequest extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'item_id',
        'status',
        'sender_confirmed_at',
        'receiver_confirmed_at',
        'shipment_requested_at',
        'shipment_requested_by',
        'shipment_approved_at',
        'shipment_approved_by',
    ];

    protected $casts = [
        'sender_confirmed_at' => 'datetime',
        'receiver_confirmed_at' => 'datetime',
        'shipment_requested_at' => 'datetime',
        'shipment_approved_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
