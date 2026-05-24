<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AbsoluteSessionTimeout
{
    /**
     * Force re-login after a fixed time since authentication (not extended by activity).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();
        $lifetimeMinutes = $isAdmin
            ? max(1, (int) config('admin.session_lifetime_minutes', 60))
            : max(0, (int) config('auth.absolute_session_lifetime', 10080));

        if ($lifetimeMinutes <= 0) {
            return $next($request);
        }

        $session = $request->session();
        $loggedInAt = $session->get('auth_logged_in_at');

        if (! $loggedInAt) {
            if ($isAdmin) {
                return $this->expireSession($request, 'Admin session is invalid. Please log in again.');
            }

            $session->put('auth_logged_in_at', now()->getTimestamp());

            return $next($request);
        }

        $loggedInAtCarbon = Carbon::createFromTimestamp((int) $loggedInAt);
        $minutesSinceLogin = $loggedInAtCarbon->diffInMinutes(now());

        if ($minutesSinceLogin < $lifetimeMinutes) {
            return $next($request);
        }

        $message = $isAdmin
            ? 'Admin session expired after '.(int) config('admin.session_lifetime_minutes', 60).' minutes. Please log in again.'
            : 'Your session has expired. Please log in again.';

        return $this->expireSession($request, $message);
    }

    protected function expireSession(Request $request, string $message): Response
    {
        Auth::logout();
        $session = $request->session();
        $session->invalidate();
        $session->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('login')->with('status', $message);
    }
}
