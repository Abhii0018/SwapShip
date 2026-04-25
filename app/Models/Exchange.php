<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exchange extends Model
{
    protected $fillable = [
        'requester_id',
        'accepter_id',
        'offered_book_id',
        'requested_book_id',
        'status',
        'cash_amount',
        'shipping_address',
        'tracking_number',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepter_id');
    }

    public function offeredBook(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'offered_book_id');
    }

    public function requestedBook(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'requested_book_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
