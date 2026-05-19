<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\EmailOtpVerificationController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVerified
{
    public function handle(Request $request, Closure $next, ?string $redirectRoute = null): Response
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->is_verified && $user->hasVerifiedEmail()) {
            return $next($request);
        }

        $redirectRoute ??= 'otp.verify.notice';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your account is not verified. Please complete OTP verification.',
                'needs_verification' => true,
            ], 403);
        }

        Auth::logout();
        $request->session()->put('pending_otp_user_id', $user->id);
        EmailOtpVerificationController::issueOtp($user);

        return redirect()->route($redirectRoute)
            ->with('status', 'Please verify your account with OTP to continue.');
    }
}
