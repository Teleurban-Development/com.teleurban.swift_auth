<?php

namespace Teleurban\SwiftAuth\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Closure;

class AuthenticatedUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role = ''): Response
    {
        if (!Auth::check()) {
            return redirect()->route('swift-auth.login')->with('error', 'You must be logged in.');
        }

        // TODO: Implement role-based access control
        // if (!empty($role) && !Auth::user()->hasRole($role)) {
        //     return redirect()->route('swift-auth.user.index')->with('error', 'Unauthorized access.');
        // }

        return $next($request);
    }
}
