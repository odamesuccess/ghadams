<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventTwoFactorDisable
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * Two-factor authentication is mandatory in GhanaDAMS.
         * Fortify normally exposes a DELETE endpoint for disabling it.
         * GhanaDAMS deliberately blocks that operation.
         */
        if (
            $request->user() !== null &&
            $request->is('user/two-factor-authentication') &&
            $request->isMethod('DELETE')
        ) {
            abort(
                403,
                'Two-factor authentication is mandatory and cannot be disabled.'
            );
        }

        return $next($request);
    }
}