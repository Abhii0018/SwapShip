<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationOtp;
use App\Support\AdminAccount;
use App\Services\OtpMailSender;
use App\Models\RegistrationPending;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Throwable;

class EmailOtpVerificationController extends Controller
{
    private const RESEND_COOLDOWN_SECONDS = 30;

    private const PENDING_COOKIE = 'pending_reg';

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
                self::issueOtp($authUser);
            }
        }

        $pending = $this->resolvePendingRegistration($request);
        if ($pending) {
            return view('auth.verify-otp', [
                'email' => (string) ($pending['email'] ?? ''),
                'resendCooldownSeconds' => $this->remainingResendSecondsFromTimestamp($pending['otp_last_sent_at'] ?? null),
                'mailError' => (string) $request->session()->get('otp_mail_error', ''),
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
                'mailError' => (string) $request->session()->get('otp_mail_error', ''),
            ]);
        }

        return redirect()->route('login')->with('status', 'Please register or login to verify your email.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pending = $this->resolvePendingRegistration($request);
        if ($pending) {
            return $this->verifyPendingRegistration($request, $pending);
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

        AdminAccount::syncRole($user);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_logged_in_at', now()->getTimestamp());

        return redirect()->to(AdminAccount::homeRouteFor($user))->with('success', 'Email verified successfully.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->resolvePendingRegistration($request);
        if ($pending) {
            return $this->resendPendingRegistrationOtp($request, $pending);
        }

        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'No pending verification found.']);
        }

        $record = EmailVerificationOtp::query()->firstOrNew(['user_id' => $user->id]);
        if ($this->remainingResendSeconds($record) > 0) {
            return back()->withErrors(['otp' => 'Please wait a few seconds before requesting again.']);
        }

        if (! self::issueOtp($user, $record)) {
            return back()
                ->withErrors(['otp' => OtpMailSender::lastErrorMessage()])
                ->with('mail_error', OtpMailSender::lastErrorMessage());
        }

        $request->session()->forget('otp_mail_error');

        return back()->with('status', 'OTP sent to your email. Check inbox and spam.');
    }

    /**
     * Store pending signup and queue OTP email. Returns cookie token on success.
     *
     * @param  array{name?: string, email: string, password: string, phone?: string|null, google_id?: string|null, role?: string, oauth_pending?: bool}  $data
     */
    public static function beginPendingRegistration(Request $request, array $data): ?string
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        try {
            RegistrationPending::purgeExpired();
        } catch (Throwable) {
            // Table may not exist until migrate runs; session fallback still works.
        }

        $request->session()->forget(['pending_otp_user_id', 'pending_registration']);

        $token = Str::random(64);
        $otp = (string) random_int(100000, 999999);
        $otpHash = Hash::make($otp);
        $now = now();

        $sessionPayload = [
            'name' => (string) ($data['name'] ?? 'User'),
            'email' => $email,
            'password' => (string) ($data['password'] ?? ''),
            'phone' => $data['phone'] ?? null,
            'google_id' => $data['google_id'] ?? null,
            'role' => (string) ($data['role'] ?? 'user'),
            'oauth_pending' => (bool) ($data['oauth_pending'] ?? false),
            'pending_expires_at' => $now->copy()->addMinutes(30)->toIso8601String(),
            'otp_hash' => $otpHash,
            'otp_attempts' => 0,
            'otp_expires_at' => $now->copy()->addMinutes(10)->toIso8601String(),
            'otp_last_sent_at' => $now->toIso8601String(),
        ];

        $request->session()->put('pending_registration', $sessionPayload);
        $request->session()->save();

        try {
            RegistrationPending::query()->where('email', $email)->delete();
            RegistrationPending::query()->create([
                'token' => $token,
                'email' => $email,
                'payload' => [
                    'name' => $sessionPayload['name'],
                    'password' => $sessionPayload['password'],
                    'phone' => $sessionPayload['phone'],
                    'google_id' => $sessionPayload['google_id'],
                    'role' => $sessionPayload['role'],
                    'oauth_pending' => $sessionPayload['oauth_pending'],
                ],
                'otp_hash' => $otpHash,
                'otp_attempts' => 0,
                'otp_expires_at' => $now->copy()->addMinutes(10),
                'otp_last_sent_at' => $now,
                'expires_at' => $now->copy()->addMinutes(30),
            ]);

            $sessionPayload['_db_token'] = $token;
            $request->session()->put('pending_registration', $sessionPayload);
        } catch (Throwable $exception) {
            report($exception);
            $token = null;
        }

        $mailSent = OtpMailSender::send($email, $sessionPayload['name'], $otp);
        $request->session()->put('otp_mail_sent', $mailSent);
        $request->session()->put('otp_mail_error', $mailSent ? null : OtpMailSender::lastErrorMessage());

        return $token;
    }

    public static function pendingCookie(?string $token): ?SymfonyCookie
    {
        if ($token === null || $token === '') {
            return null;
        }

        $secure = str_starts_with((string) config('app.url'), 'https://');

        return Cookie::make(
            self::PENDING_COOKIE,
            $token,
            30,
            '/',
            null,
            $secure,
            true,
            false,
            'lax'
        );
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

        return OtpMailSender::send((string) $user->email, (string) $user->name, $otp);
    }

    protected function resolvePendingRegistration(Request $request): ?array
    {
        $fromSession = $this->pendingRegistrationFromSession($request);
        if ($fromSession) {
            return $fromSession;
        }

        $token = (string) $request->cookie(self::PENDING_COOKIE, '');
        if ($token === '') {
            return null;
        }

        try {
            $record = RegistrationPending::query()->where('token', $token)->first();
        } catch (Throwable) {
            return null;
        }

        if (! $record || $record->isExpired()) {
            return null;
        }

        $payload = $record->payload ?? [];

        return [
            'name' => (string) ($payload['name'] ?? 'User'),
            'email' => (string) $record->email,
            'password' => (string) ($payload['password'] ?? ''),
            'phone' => $payload['phone'] ?? null,
            'google_id' => $payload['google_id'] ?? null,
            'role' => (string) ($payload['role'] ?? 'user'),
            'oauth_pending' => (bool) ($payload['oauth_pending'] ?? false),
            'pending_expires_at' => $record->expires_at?->toIso8601String(),
            'otp_hash' => (string) $record->otp_hash,
            'otp_attempts' => (int) $record->otp_attempts,
            'otp_expires_at' => $record->otp_expires_at?->toIso8601String(),
            'otp_last_sent_at' => $record->otp_last_sent_at?->toIso8601String(),
            '_db_token' => $record->token,
        ];
    }

    protected function pendingRegistrationFromSession(Request $request): ?array
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

    protected function pendingUser(Request $request): ?User
    {
        $userId = (int) $request->session()->get('pending_otp_user_id');

        return $userId ? User::query()->find($userId) : null;
    }

    protected function verifyPendingRegistration(Request $request, array $pending): RedirectResponse
    {
        $expiresAt = isset($pending['otp_expires_at'])
            ? Carbon::parse((string) $pending['otp_expires_at'])
            : null;

        if (! $expiresAt || now()->greaterThan($expiresAt)) {
            return back()->withErrors(['otp' => 'OTP expired. Please resend.']);
        }

        $attempts = (int) ($pending['otp_attempts'] ?? 0);
        if ($attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many attempts. Please resend OTP.']);
        }

        $otpHash = (string) ($pending['otp_hash'] ?? '');
        if ($otpHash === '' || ! Hash::check((string) $request->input('otp'), $otpHash)) {
            $this->incrementPendingAttempts($request, $pending);

            return back()->withErrors(['otp' => 'Invalid OTP.']);
        }

        $plainPassword = (string) ($pending['password'] ?? '');
        if ($plainPassword === '') {
            $plainPassword = Str::password(24);
        }

        $email = (string) ($pending['email'] ?? '');
        $role = (string) ($pending['role'] ?? 'user');
        if (AdminAccount::isAdminEmail($email)) {
            $role = 'admin';
        }

        $user = User::create([
            'name' => (string) ($pending['name'] ?? ''),
            'email' => $email,
            'password' => $plainPassword,
            'google_id' => $pending['google_id'] ?? null,
            'phone' => $pending['phone'] ?? null,
            'role' => $role,
            'email_verified_at' => now(),
            'is_verified' => true,
        ]);

        AdminAccount::syncRole($user);

        $this->clearPendingRegistration($request, $pending);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_logged_in_at', now()->getTimestamp());

        return redirect()->to(AdminAccount::homeRouteFor($user))->with('success', 'Email verified successfully.');
    }

    protected function resendPendingRegistrationOtp(Request $request, array $pending): RedirectResponse
    {
        if ($this->remainingResendSecondsFromTimestamp($pending['otp_last_sent_at'] ?? null) > 0) {
            return back()->withErrors(['otp' => 'Please wait a few seconds before requesting again.']);
        }

        $email = (string) ($pending['email'] ?? '');
        $name = (string) ($pending['name'] ?? 'User');
        if ($email === '') {
            return redirect()->route('login')->withErrors(['email' => 'No pending verification found.']);
        }

        $otp = (string) random_int(100000, 999999);
        $otpHash = Hash::make($otp);
        $now = now();

        $pending['otp_hash'] = $otpHash;
        $pending['otp_attempts'] = 0;
        $pending['otp_expires_at'] = $now->copy()->addMinutes(10)->toIso8601String();
        $pending['otp_last_sent_at'] = $now->toIso8601String();

        $request->session()->put('pending_registration', $pending);
        $request->session()->save();

        $token = (string) ($pending['_db_token'] ?? '');
        if ($token !== '') {
            try {
                RegistrationPending::query()->where('token', $token)->update([
                    'otp_hash' => $otpHash,
                    'otp_attempts' => 0,
                    'otp_expires_at' => $now->copy()->addMinutes(10),
                    'otp_last_sent_at' => $now,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (! OtpMailSender::send($email, $name, $otp)) {
            $error = OtpMailSender::lastErrorMessage();
            $request->session()->put('otp_mail_error', $error);

            return back()
                ->withErrors(['otp' => $error])
                ->with('mail_error', $error);
        }

        $request->session()->forget('otp_mail_error');

        return back()->with('status', 'OTP sent to your email. Check inbox and spam.');
    }

    protected function incrementPendingAttempts(Request $request, array $pending): void
    {
        $pending['otp_attempts'] = (int) ($pending['otp_attempts'] ?? 0) + 1;
        $request->session()->put('pending_registration', $pending);

        $token = (string) ($pending['_db_token'] ?? '');
        if ($token !== '') {
            try {
                RegistrationPending::query()->where('token', $token)->update([
                    'otp_attempts' => (int) $pending['otp_attempts'],
                ]);
            } catch (Throwable) {
                //
            }
        }
    }

    protected function clearPendingRegistration(Request $request, array $pending): void
    {
        $request->session()->forget('pending_registration');
        $request->session()->forget('pending_otp_user_id');

        $token = (string) ($pending['_db_token'] ?? '');
        if ($token !== '') {
            try {
                RegistrationPending::query()->where('token', $token)->delete();
            } catch (Throwable) {
                //
            }
        }

        Cookie::queue(Cookie::forget(self::PENDING_COOKIE));
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
