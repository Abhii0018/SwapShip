<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login', [
            'prefillEmail' => request()->query('email', old('email')),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = $request->user();
        if ($user && (! $user->hasVerifiedEmail() || ! $user->is_verified)) {
            Auth::logout();
            $request->session()->put('pending_otp_user_id', $user->id);
            $otpSent = EmailOtpVerificationController::issueOtp($user);

            if (! $otpSent) {
                return redirect()->route('otp.verify.notice')
                    ->withErrors(['otp' => 'Could not send OTP email right now. Please use resend after a few seconds.']);
            }

            return redirect()->route('otp.verify.notice')
                ->with('status', 'Please verify your account with OTP to continue.');
        }

        $request->session()->regenerate();
        $request->session()->put('auth_logged_in_at', now()->getTimestamp());

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
