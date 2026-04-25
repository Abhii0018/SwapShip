<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOtp extends Model
{
    protected $fillable = [
        'order_id',
        'code',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'sms_sent_at',
        'generated_for_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'sms_sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function generatedForUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_for_user_id');
    }
}
