<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'author',
        'description',
        'condition',
        'exchange_type',
        'price',
        'images',
        'locked',
    ];

    protected $casts = [
        'images' => 'array',
        'locked' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(BookImage::class);
    }

    // Legacy alias for Item compatibility
    public function toItem(): Item
    {
        return new Item($this->attributesToArray());
    }
}
