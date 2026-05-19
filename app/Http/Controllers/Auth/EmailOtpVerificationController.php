<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationOtpMail;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\View\View;

class EmailOtpVerificationController extends Controller
{
    private const RESEND_COOLDOWN_SECONDS = 30;

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $authUser */
            $authUser = Auth::user();
            if ($authUser->is_verified && $authUser->hasVerifiedEmail()) {
                return redirect()->route('home');
            }

            Auth::logout();
            $request->session()->put('pending_otp_user_id', $authUser->id);
            if (! EmailVerificationOtp::query()->where('user_id', $authUser->id)->exists()) {
                self::issueOtp($authUser); // sends in background after response
            }
        }

        $pendingRegistration = $this->pendingRegistration($request);
        if ($pendingRegistration) {
            return view('auth.verify-otp', [
                'email' => (string) ($pendingRegistration['email'] ?? ''),
                'resendCooldownSeconds' => $this->remainingResendSecondsFromTimestamp($pendingRegistration['otp_last_sent_at'] ?? null),
            ]);
        }

        $user = $this->pendingUser($request);
        if ($user) {
            if ($user->hasVerifiedEmail() && $user->is_verified) {
                $request->session()->forget('pending_otp_user_id');

                return redirect()->route('login')->with('status', 'Account already verified. Please login.');
            }

            $record = EmailVerificationOtp::query()->where('user_id', $user->id)->first();

            return view('auth.verify-otp', [
                'email' => $user->email,
                'resendCooldownSeconds' => $this->remainingResendSeconds($record),
            ]);
        }

        return redirect()->route('login')->with('status', 'Please register or login to verify your email.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pendingRegistration = $this->pendingRegistration($request);
        if ($pendingRegistration) {
            return $this->verifyPendingRegistration($request);
        }

        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'No pending verification found. Please login or register again.']);
        }

        $record = EmailVerificationOtp::query()->where('user_id', $user->id)->first();
        if (! $record) {
            return back()->withErrors(['otp' => 'OTP has not been generated. Please resend OTP.']);
        }

        if (now()->greaterThan($record->expires_at)) {
            return back()->withErrors(['otp' => 'OTP expired. Please resend.']);
        }

        if ($record->attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many attempts. Please resend OTP.']);
        }

        if (! Hash::check((string) $request->input('otp'), $record->otp_hash)) {
            $record->increment('attempts');

            return back()->withErrors(['otp' => 'Invalid OTP.']);
        }

        $user->forceFill(['email_verified_at' => now(), 'is_verified' => true])->save();
        $record->delete();
        $request->session()->forget('pending_otp_user_id');

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_logged_in_at', now()->getTimestamp());

        return redirect()->route('home')->with('success', 'Email verified successfully.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $pendingRegistration = $this->pendingRegistration($request);
        if ($pendingRegistration) {
            return $this->resendPendingRegistrationOtp($request);
        }

        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'No pending verification found.']);
        }

        $record = EmailVerificationOtp::query()->firstOrNew(['user_id' => $user->id]);
        if ($this->remainingResendSeconds($record) > 0) {
            return back()->withErrors(['otp' => 'Please wait a few seconds before requesting again.']);
        }

        if (! $this->issueOtp($user, $record)) {
            return back()->withErrors(['otp' => 'Could not send OTP email right now. Please try again shortly.']);
        }

        return back()->with('status', 'OTP sent to your email.');
    }

    /**
     * Store pending signup data in session and email a verification OTP.
     *
     * @param  array{name?: string, email: string, password: string, phone?: string|null, google_id?: string|null, role?: string, oauth_pending?: bool}  $data
     */
    public static function beginPendingRegistration(Request $request, array $data): bool
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            return false;
        }

        $request->session()->forget(['pending_otp_user_id', 'pending_registration']);
        $request->session()->put('pending_registration', [
            'name' => (string) ($data['name'] ?? 'User'),
            'email' => $email,
            'password' => (string) ($data['password'] ?? ''),
            'phone' => $data['phone'] ?? null,
            'google_id' => $data['google_id'] ?? null,
            'role' => (string) ($data['role'] ?? 'user'),
            'oauth_pending' => (bool) ($data['oauth_pending'] ?? false),
            'pending_expires_at' => now()->addMinutes(30)->toIso8601String(),
        ]);
        $request->session()->save();

        return self::issueOtpForPendingRegistration($request);
    }

    public static function issueOtpForPendingRegistration(Request $request): bool
    {
        $pendingRegistration = (array) $request->session()->get('pending_registration', []);
        $email = (string) ($pendingRegistration['email'] ?? '');
        $name = (string) ($pendingRegistration['name'] ?? 'User');

        if ($email === '') {
            return false;
        }

        $otp = (string) random_int(100000, 999999);
        $pendingRegistration['otp_hash'] = Hash::make($otp);
        $pendingRegistration['otp_attempts'] = 0;
        $pendingRegistration['otp_expires_at'] = now()->addMinutes(10)->toIso8601String();
        $pendingRegistration['otp_last_sent_at'] = now()->toIso8601String();
        $request->session()->put('pending_registration', $pendingRegistration);
        $request->session()->save();

        self::dispatchOtpMail($email, $name, $otp);

        return true;
    }

    public static function issueOtp(User $user, ?EmailVerificationOtp $record = null): bool
    {
        $record ??= EmailVerificationOtp::query()->firstOrNew(['user_id' => $user->id]);

        $otp = (string) random_int(100000, 999999);
        $record->otp_hash = Hash::make($otp);
        $record->attempts = 0;
        $record->expires_at = now()->addMinutes(10);
        $record->last_sent_at = now();
        $record->save();

        self::dispatchOtpMail((string) $user->email, (string) $user->name, $otp);

        return true;
    }

    protected static function dispatchOtpMail(string $email, string $name, string $otp): void
    {
        dispatch(static function () use ($email, $name, $otp): void {
            try {
                $recipient = new User([
                    'name' => $name,
                    'email' => $email,
                ]);

                Mail::to($email)->send(new EmailVerificationOtpMail($recipient, $otp));
            } catch (Throwable $exception) {
                report($exception);
            }
        })->afterResponse();
    }

    protected function pendingUser(Request $request): ?User
    {
        $userId = (int) $request->session()->get('pending_otp_user_id');

        return $userId ? User::query()->find($userId) : null;
    }

    protected function pendingRegistration(Request $request): ?array
    {
        $pendingRegistration = $request->session()->get('pending_registration');
        if (! is_array($pendingRegistration)) {
            return null;
        }

        $pendingExpiresAt = (string) ($pendingRegistration['pending_expires_at'] ?? '');
        if ($pendingExpiresAt !== '' && now()->greaterThan(Carbon::parse($pendingExpiresAt))) {
            $request->session()->forget('pending_registration');

            return null;
        }

        return $pendingRegistration;
    }

    protected function verifyPendingRegistration(Request $request): RedirectResponse
    {
        $pendingRegistration = $this->pendingRegistration($request);
        if (! $pendingRegistration) {
            return redirect()->route('login')->withErrors(['email' => 'No pending verification found.']);
        }

        $expiresAt = isset($pendingRegistration['otp_expires_at'])
            ? Carbon::parse((string) $pendingRegistration['otp_expires_at'])
            : null;

        if (! $expiresAt || now()->greaterThan($expiresAt)) {
            return back()->withErrors(['otp' => 'OTP expired. Please resend.']);
        }

        $attempts = (int) ($pendingRegistration['otp_attempts'] ?? 0);
        if ($attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many attempts. Please resend OTP.']);
        }

        $otpHash = (string) ($pendingRegistration['otp_hash'] ?? '');
        if ($otpHash === '' || ! Hash::check((string) $request->input('otp'), $otpHash)) {
            $pendingRegistration['otp_attempts'] = $attempts + 1;
            $request->session()->put('pending_registration', $pendingRegistration);

            return back()->withErrors(['otp' => 'Invalid OTP.']);
        }

        $plainPassword = (string) ($pendingRegistration['password'] ?? '');
        if ($plainPassword === '') {
            $plainPassword = Str::password(24);
        }

        $user = User::create([
            'name' => (string) ($pendingRegistration['name'] ?? ''),
            'email' => (string) ($pendingRegistration['email'] ?? ''),
            'password' => $plainPassword,
            'google_id' => $pendingRegistration['google_id'] ?? null,
            'phone' => $pendingRegistration['phone'] ?? null,
            'role' => (string) ($pendingRegistration['role'] ?? 'user'),
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);

        $request->session()->forget('pending_registration');
        $request->session()->forget('pending_otp_user_id');

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_logged_in_at', now()->getTimestamp());

        return redirect()->route('home')->with('success', 'Email verified successfully.');
    }

    protected function resendPendingRegistrationOtp(Request $request): RedirectResponse
    {
        $pendingRegistration = $this->pendingRegistration($request);
        if (! $pendingRegistration) {
            return redirect()->route('login')->withErrors(['email' => 'No pending verification found.']);
        }

        if ($this->remainingResendSecondsFromTimestamp($pendingRegistration['otp_last_sent_at'] ?? null) > 0) {
            return back()->withErrors(['otp' => 'Please wait a few seconds before requesting again.']);
        }

        if (! self::issueOtpForPendingRegistration($request)) {
            return back()->withErrors(['otp' => 'Could not send OTP email right now. Please try again shortly.']);
        }

        return back()->with('status', 'OTP sent to your email.');
    }

    protected function remainingResendSeconds(?EmailVerificationOtp $record): int
    {
        if (! $record || ! $record->last_sent_at) {
            return 0;
        }

        $elapsedSeconds = (int) now()->diffInSeconds($record->last_sent_at, false);

        if ($elapsedSeconds < 0) {
            return self::RESEND_COOLDOWN_SECONDS;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsedSeconds);
    }

    protected function remainingResendSecondsFromTimestamp(?string $timestamp): int
    {
        if (! $timestamp) {
            return 0;
        }

        $sentAt = Carbon::parse($timestamp);
        $elapsedSeconds = (int) now()->diffInSeconds($sentAt, false);

        if ($elapsedSeconds < 0) {
            return self::RESEND_COOLDOWN_SECONDS;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsedSeconds);
    }
}
