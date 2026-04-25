<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookImage extends Model
{
    protected $fillable = [
        'book_id',
        'url',
        'public_id',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    // Legacy alias for ItemImage compatibility
    public function toItemImage(): ItemImage
    {
        return new ItemImage($this->attributesToArray());
    }
}
