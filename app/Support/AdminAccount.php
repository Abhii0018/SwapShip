<?php

namespace App\Support;

use App\Models\User;

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
     * Admin accounts always require email OTP after password or Google sign-in.
     */
    public static function requiresLoginOtp(User $user): bool
    {
        return self::isAdminEmail($user->email);
    }
}
