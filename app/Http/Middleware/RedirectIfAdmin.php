<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep admins on admin routes — block marketplace user flows.
 */
class RedirectIfAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isAdmin()) {
            return redirect()->route('admin.dashboard')
                ->with('status', 'Admin accounts use the admin panel.');
        }

        return $next($request);
    }
}
