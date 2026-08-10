<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class AdminAccount
{
    public static function adminEmails(): array
    {
        return config('admin.emails', []);
    }

    public static function isAdminEmail(?string $email): bool
    {
        $email = mb_strtolower(trim((string) $email));
        if ($email === '') {
            return false;
        }

        return in_array($email, self::adminEmails(), true);
    }

    public static function syncRole(User $user): User
    {
        if (self::isAdminEmail($user->email) && $user->role !== 'admin') {
            $user->role = 'admin';
            $user->save();
        }

        return $user;
    }

    public static function homeRouteFor(User $user): string
    {
        self::syncRole($user);

        return $user->isAdmin()
            ? route('admin.dashboard')
            : route('home');
    }

    /**
     * Admin accounts still use OTP for Google sign-in flows.
     */
    public static function requiresLoginOtp(User $user): bool
    {
        return self::isAdminEmail($user->email);
    }

    public static function sessionLifetimeMinutes(): int
    {
        return max(1, (int) config('admin.session_lifetime_minutes', 60));
    }

    public static function markSessionStarted(Request $request): void
    {
        $request->session()->put('auth_logged_in_at', now()->getTimestamp());
    }
}
