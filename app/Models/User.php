<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'email',
    'google_id',
    'password',
    'role',
    'is_banned',
    'phone',
    'address',
    'profile_photo_path',
    'avatar_preset',
    'age',
    'gender',
    'city',
    'state',
    'pincode',
    'location',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, MustVerifyEmailTrait;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }

    public function profilePhotoUrl(): ?string
    {
        if ($this->profile_photo_path) {
            return '/storage/'.$this->profile_photo_path;
        }

        if ($this->avatar_preset) {
            return (string) $this->avatar_preset;
        }

        return null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : 'U';
    }

    public function firstName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $first = $parts[0] ?? '';
        return $first !== '' ? $first : (string) $this->name;
    }

    public function hasCompletedProfile(): bool
    {
        return filled($this->phone)
            && filled($this->age)
            && filled($this->gender)
            && filled($this->city)
            && filled($this->state)
            && filled($this->pincode)
            && filled($this->location);
    }

    public function profileCompletionPercent(): int
    {
        $fields = [
            'phone' => $this->phone,
            'age' => $this->age,
            'gender' => $this->gender,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'location' => $this->location,
            'profile_identity' => $this->profile_photo_path ?: $this->avatar_preset,
        ];

        $completed = collect($fields)->filter(fn ($value) => filled($value))->count();
        $total = count($fields);

        if ($total === 0) {
            return 0;
        }

        return (int) round(($completed / $total) * 100);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function sentExchangeRequests(): HasMany
    {
        return $this->hasMany(ExchangeRequest::class, 'sender_id');
    }

    public function receivedExchangeRequests(): HasMany
    {
        return $this->hasMany(ExchangeRequest::class, 'receiver_id');
    }
}
