<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        if (
            Auth::user()->force_password_change &&
            ! $request->routeIs(
                'force-password.change',
                'force-password.update',
                'logout'
            )
        ) {
            return redirect()->route('force-password.change');
        }

        return $next($request);
    }
}
