<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVerified
{
    public function handle(Request $request, Closure $next, string $redirectRoute = null): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        if ($user->is_verified) {
            return $next($request);
        }

        // Users not yet verified — send them to OTP verification
        $redirectRoute ??= 'otp.verify.notice';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your account is not verified. Please complete OTP verification.',
                'needs_verification' => true,
            ], 403);
        }

        return redirect()->route($redirectRoute)
            ->with('status', 'Please verify your account before continuing.');
    }
}