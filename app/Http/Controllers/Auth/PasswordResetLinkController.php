<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 15;

    /**
     * Display the forgot password view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset OTP to email.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim((string) $request->input('email')));
        $user = User::query()->where('email', $email)->first();

        // Keep response generic to avoid revealing whether the email exists.
        if (! $user) {
            return back()->with('status', 'If this email exists, an OTP has been sent.');
        }

        $otp = (string) random_int(100000, 999999);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        Mail::to($email)->send(new PasswordResetOtpMail($user, $otp, self::OTP_EXPIRY_MINUTES));
        $request->session()->put('password_reset_email', $email);

        return back()
            ->with('status', 'OTP sent to your email. Enter OTP and your new password below.')
            ->with('otp_sent', true)
            ->withInput(['email' => $email]);
    }

    /**
     * Verify OTP and update password.
     *
     * @throws ValidationException
     */
    public function resetWithOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = Str::lower(trim((string) $request->input('email')));
        $otp = (string) $request->input('otp');

        $resetRow = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $resetRow) {
            throw ValidationException::withMessages([
                'otp' => 'OTP not found. Please request a new OTP.',
            ]);
        }

        $createdAt = $resetRow->created_at ? Carbon::parse((string) $resetRow->created_at) : null;
        if (! $createdAt || now()->greaterThan($createdAt->copy()->addMinutes(self::OTP_EXPIRY_MINUTES))) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'otp' => 'OTP has expired. Please request a new OTP.',
            ]);
        }

        if (! Hash::check($otp, (string) $resetRow->token)) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid OTP.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No account found for this email.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        $request->session()->forget('password_reset_email');

        return redirect()->route('login')->with('status', 'Password reset successful. Please log in.');
    }
}
