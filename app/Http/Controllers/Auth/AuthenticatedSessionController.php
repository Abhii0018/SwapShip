<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AdminAccount;
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

        $needsOtp = $user && (
            AdminAccount::requiresLoginOtp($user)
            || ! $user->hasVerifiedEmail()
            || ! $user->is_verified
        );

        if ($needsOtp) {
            Auth::logout();
            $request->session()->put('pending_otp_user_id', $user->id);
            EmailOtpVerificationController::issueOtp($user);

            $message = AdminAccount::requiresLoginOtp($user)
                ? 'Admin login requires email OTP. Check your inbox for the 6-digit code.'
                : 'Enter the OTP sent to your email. It may take up to a minute to arrive.';

            return redirect()->route('otp.verify.notice')->with('status', $message);
        }

        if ($user) {
            AdminAccount::syncRole($user);
        }
        $request->session()->regenerate();
        $request->session()->put('auth_logged_in_at', now()->getTimestamp());

        return redirect()->intended(AdminAccount::homeRouteFor($user));
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
