<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        /*
         * These endpoints are necessary to complete the mandatory
         * two-factor setup flow. Everything else is blocked until
         * two_factor_confirmed_at is populated.
         */
        if (
            $request->is('settings/two-factor') ||
            $request->is('settings/two-factor/*') ||
            $request->is('user/two-factor-authentication') ||
            $request->is('user/confirmed-two-factor-authentication') ||
            $request->is('user/two-factor-qr-code') ||
            $request->is('user/two-factor-secret-key') ||
            $request->is('user/two-factor-recovery-codes') ||
            $request->is('confirm-password') ||
            $request->is('user/confirm-password') ||
            $request->is('logout')
        ) {
            return $next($request);
        }

        return redirect()->route('two-factor.show');
    }
}