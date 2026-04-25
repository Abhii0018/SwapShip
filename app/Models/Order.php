<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'shipment_id',
        'buyer_id',
        'seller_id',
        'payment_method',
        'gateway',
        'payment_reference',
        'gateway_order_id',
        'upfront_gateway_order_id',
        'remaining_gateway_order_id',
        'item_amount',
        'negotiated_item_amount',
        'shipping_amount',
        'platform_fee',
        'total_amount',
        'upfront_amount',
        'remaining_amount',
        'upfront_paid_at',
        'remaining_paid_at',
        'upfront_payment_reference',
        'remaining_payment_reference',
        'second_payment_required_before_otp',
        'payment_status',
        'settlement_status',
        'paid_at',
        'collected_at',
        'released_at',
        'delivery_verified_at',
    ];

    protected $casts = [
        'item_amount' => 'decimal:2',
        'negotiated_item_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'upfront_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'second_payment_required_before_otp' => 'boolean',
        'upfront_paid_at' => 'datetime',
        'remaining_paid_at' => 'datetime',
        'paid_at' => 'datetime',
        'collected_at' => 'datetime',
        'released_at' => 'datetime',
        'delivery_verified_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function deliveryOtps(): HasMany
    {
        return $this->hasMany(DeliveryOtp::class);
    }

    public function smsAuditLogs(): HasMany
    {
        return $this->hasMany(SmsAuditLog::class);
    }
}
