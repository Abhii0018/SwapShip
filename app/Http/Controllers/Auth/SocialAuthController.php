<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google login is not configured yet.',
            ]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google authentication failed. Please try again.',
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleUser->id)
            ->orWhere('email', $googleUser->email)
            ->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => $googleUser->name ?: 'Google User',
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                'role' => 'user',
                'password' => Str::password(24),
                'email_verified_at' => now(),
            ]);
        } elseif (! $user->google_id) {
            $user->google_id = $googleUser->id;
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->save();
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
