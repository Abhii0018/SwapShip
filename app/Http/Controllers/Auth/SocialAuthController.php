<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Google OAuth callback error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Google authentication failed. Please try again.',
            ]);
        }

        $email = mb_strtolower(trim((string) $googleUser->email));

        $user = User::query()
            ->where(function ($query) use ($googleUser, $email) {
                $query->where('google_id', $googleUser->id)
                    ->orWhere('email', $email);
            })
            ->first();

        if (! $user) {
            $pendingToken = EmailOtpVerificationController::beginPendingRegistration($request, [
                'name' => $googleUser->name ?: 'Google User',
                'email' => $email,
                'password' => Str::password(24),
                'google_id' => $googleUser->id,
                'oauth_pending' => true,
            ]);

            $mailSent = (bool) $request->session()->get('otp_mail_sent', false);

            $response = redirect()->route('otp.verify.notice')
                ->with(
                    'status',
                    $mailSent ? 'We sent a 6-digit OTP to your email. Check inbox and spam.' : null
                )
                ->with(
                    'mail_error',
                    $mailSent ? null : 'OTP email could not be sent. Use Resend OTP after checking MAIL_* on Render.'
                );

            $cookie = EmailOtpVerificationController::pendingCookie($pendingToken);
            if ($cookie) {
                $response->withCookie($cookie);
            }

            return $response;
        }

        if (! $user->google_id) {
            $user->google_id = $googleUser->id;
        }

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
