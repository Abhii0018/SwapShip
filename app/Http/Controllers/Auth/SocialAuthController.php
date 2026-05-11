<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Google OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('login')->withErrors([
                'email' => 'Google authentication failed: ' . $e->getMessage() . '. Please try again.',
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleUser->id)
            ->orWhere('email', $googleUser->email)
            ->first();

        if (! $user) {
            // New OAuth user — store pending registration and require OTP verification
            $otp = (string) random_int(100000, 999999);

            $request->session()->forget('pending_otp_user_id', 'pending_registration');
            $request->session()->put('pending_registration', [
                'name' => $googleUser->name ?: 'Google User',
                'email' => mb_strtolower(trim((string) $googleUser->email)),
                'password' => Hash::make(Str::password(24)),
                'google_id' => $googleUser->id,
                'role' => 'user',
                'pending_expires_at' => now()->addMinutes(10)->toIso8601String(),
                'otp_hash' => Hash::make($otp),
                'otp_attempts' => 0,
                'otp_expires_at' => now()->addMinutes(10)->toIso8601String(),
                'otp_last_sent_at' => now()->toIso8601String(),
                'oauth_pending' => true,
            ]);

            try {
                $tempUser = new User([
                    'name' => $googleUser->name ?: 'Google User',
                    'email' => $googleUser->email,
                ]);
                \Illuminate\Support\Facades\Mail::to($googleUser->email)
                    ->queue(new \App\Mail\EmailVerificationOtpMail($tempUser, $otp));
            } catch (\Throwable $exception) {
                report($exception);
            }

            return redirect()->route('otp.verify.notice')
                ->with('status', 'Welcome! Complete OTP verification to activate your account.');
        }

        // Existing user — login directly (OTP only required for new registrations)
        if (! $user->google_id) {
            $user->google_id = $googleUser->id;
        }

        // Mark as verified since Google OAuth already verified the email
        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
        }
        if (! $user->is_verified) {
            $user->is_verified = true;
        }
        
        $user->save();

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('auth_logged_in_at', now()->getTimestamp());

        return redirect()->intended(route('home'));
    }
}
