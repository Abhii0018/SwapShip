<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RegistrationPending extends Model
{
    protected $table = 'registration_pending';

    protected $fillable = [
        'token',
        'email',
        'payload',
        'otp_hash',
        'otp_attempts',
        'otp_expires_at',
        'otp_last_sent_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'otp_attempts' => 'integer',
            'otp_expires_at' => 'datetime',
            'otp_last_sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->greaterThan($this->expires_at);
    }

    public function otpIsExpired(): bool
    {
        return $this->otp_expires_at && now()->greaterThan($this->otp_expires_at);
    }

    public static function purgeExpired(): void
    {
        static::query()
            ->where('expires_at', '<', now())
            ->delete();
    }
}
