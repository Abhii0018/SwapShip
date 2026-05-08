<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AbsoluteSessionTimeout
{
    /**
     * Force a re-login after a fixed amount of time since the user first authenticated,
     * independent of idle/poll activity.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $lifetime = (int) config('auth.absolute_session_lifetime', 720);
        if ($lifetime <= 0) {
            return $next($request);
        }

        $session = $request->session();
        $loggedInAt = $session->get('auth_logged_in_at');

        if (! $loggedInAt) {
            $session->put('auth_logged_in_at', now()->getTimestamp());
            return $next($request);
        }

        $loggedInAtCarbon = Carbon::createFromTimestamp((int) $loggedInAt);
        $minutesSinceLogin = $loggedInAtCarbon->diffInMinutes(now());

        if ($minutesSinceLogin < $lifetime) {
            return $next($request);
        }

        Auth::logout();
        $session->invalidate();
        $session->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your session has expired. Please log in again.',
            ], 401);
        }

        return redirect()->route('login')->with('status', 'Your session has expired. Please log in again.');
    }
}
