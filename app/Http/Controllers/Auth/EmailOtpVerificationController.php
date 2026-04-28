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
use Throwable;
use Illuminate\View\View;

class EmailOtpVerificationController extends Controller
{
    private const RESEND_COOLDOWN_SECONDS = 30;

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if ($user) {
            if ($user->hasVerifiedEmail()) {
                $request->session()->forget('pending_otp_user_id');
                return redirect()->route('login')->with('status', 'Email already verified. Please login.');
            }

            $record = EmailVerificationOtp::query()->where('user_id', $user->id)->first();

            return view('auth.verify-otp', [
                'email' => $user->email,
                'resendCooldownSeconds' => $this->remainingResendSeconds($record),
            ]);
        }

        $pendingRegistration = $this->pendingRegistration($request);
        if (! $pendingRegistration) {
            return redirect()->route('login');
        }

        return view('auth.verify-otp', [
            'email' => (string) ($pendingRegistration['email'] ?? ''),
            'resendCooldownSeconds' => $this->remainingResendSecondsFromTimestamp($pendingRegistration['otp_last_sent_at'] ?? null),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $this->pendingUser($request);
        if (! $user) {
            return $this->verifyPendingRegistration($request);
        }

        $record = EmailVerificationOtp::query()->where('user_id', $user->id)->first();
        if (! $record) {
            return back()->withErrors(['otp' => 'OTP has not been generated.']);
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

        $user->forceFill(['email_verified_at' => now()])->save();
        $record->delete();
        $request->session()->forget('pending_otp_user_id');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Email verified successfully.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return $this->resendPendingRegistrationOtp($request);
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

        try {
            $tempUser = new User([
                'name' => $name,
                'email' => $email,
            ]);

            Mail::to($email)->send(new EmailVerificationOtpMail($tempUser, $otp));
            return true;
        } catch (Throwable $exception) {
            report($exception);
            return false;
        }
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

        try {
            Mail::to($user->email)->send(new EmailVerificationOtpMail($user, $otp));
            return true;
        } catch (Throwable $exception) {
            report($exception);
            return false;
        }
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

        $user = User::create([
            'name' => (string) ($pendingRegistration['name'] ?? ''),
            'email' => (string) ($pendingRegistration['email'] ?? ''),
            'password' => (string) ($pendingRegistration['password'] ?? ''),
            'phone' => $pendingRegistration['phone'] ?? null,
            'role' => (string) ($pendingRegistration['role'] ?? 'user'),
            'email_verified_at' => now(),
        ]);

        $request->session()->forget('pending_registration');
        $request->session()->forget('pending_otp_user_id');

        Auth::login($user);
        $request->session()->regenerate();

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

        // Guard against clock/timestamp skew so UI cooldown never exceeds 30 seconds.
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
