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
    public function create(): View
    {
        return view('auth.login', [
            'prefillEmail' => request()->query('email', old('email')),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = $request->user();
        AdminAccount::syncRole($user);

        // Admin: password OK → email OTP → admin dashboard (every login).
        if (AdminAccount::requiresLoginOtp($user)) {
            Auth::logout();
            $request->session()->put('pending_otp_user_id', $user->id);
            $request->session()->put('admin_login_otp', true);
            EmailOtpVerificationController::issueOtp($user);

            return redirect()
                ->route('otp.verify.notice')
                ->with('status', 'Password verified. Enter the 6-digit OTP sent to your email to open the admin dashboard.');
        }

        if (! $user->hasVerifiedEmail() || ! $user->is_verified) {
            Auth::logout();
            $request->session()->put('pending_otp_user_id', $user->id);
            EmailOtpVerificationController::issueOtp($user);

            return redirect()
                ->route('otp.verify.notice')
                ->with('status', 'Enter the OTP sent to your email. It may take up to a minute to arrive.');
        }

        $request->session()->regenerate();
        AdminAccount::markSessionStarted($request);

        return redirect()->intended(AdminAccount::homeRouteFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
