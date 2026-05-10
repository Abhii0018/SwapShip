<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Message extends Model
{
    protected $fillable = [
        'exchange_request_id',
        'sender_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'read_at',
        'deleted_for_sender_at',
        'deleted_for_receiver_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'deleted_for_sender_at' => 'datetime',
        'deleted_for_receiver_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Message $message) {
            Cache::forget('nav_notifications:' . $message->sender_id);
        });

        static::updated(function (Message $message) {
            Cache::forget('nav_notifications:' . $message->sender_id);
        });
    }

    public function attachmentUrl(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return '/storage/'.$this->attachment_path;
    }

    public function exchangeRequest(): BelongsTo
    {
        return $this->belongsTo(ExchangeRequest::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
