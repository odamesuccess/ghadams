<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticationEnabled
{
    /**
     * Require every authenticated GhanaDAMS user to complete confirmed TOTP 2FA.
     *
     * The middleware is placed in the web stack so it also covers authenticated
     * routes that are added outside the application's main protected route group.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Password change must happen before 2FA setup when an administrator has
        // explicitly forced the user's password to be changed.
        if ((bool) $user->force_password_change) {
            return $next($request);
        }

        // 2FA is mandatory. Do not expose Fortify's disable endpoint even if a
        // user manually crafts the request.
        if ($request->routeIs('two-factor.disable')) {
            abort(403, 'Two-factor authentication is required for all GhanaDAMS users and cannot be disabled.');
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        // These endpoints are required to display, password-confirm and complete
        // the Fortify TOTP setup flow. Logout is kept available at all times.
        if ($this->isTwoFactorSetupRequest($request)) {
            return $next($request);
        }

        return redirect()
            ->route('two-factor.show')
            ->with('status', 'Two-factor authentication is required. Complete setup to continue to GhanaDAMS.');
    }

    private function isTwoFactorSetupRequest(Request $request): bool
    {
        if ($request->routeIs(
            'two-factor.show',
            'two-factor.enable',
            'two-factor.confirm',
            'two-factor.restart',
            'two-factor.qr-code',
            'two-factor.secret-key',
            'two-factor.recovery-codes',
            'password.confirm',
            'password.confirm.store',
            'force-password.change',
            'force-password.update',
            'logout',
        )) {
            return true;
        }

        // Route-name fallbacks keep the setup flow safe across compatible
        // Fortify point releases where a generated route name may differ.
        return $request->is([
            'settings/two-factor',
            'settings/two-factor/restart',
            'user/two-factor-authentication',
            'user/confirmed-two-factor-authentication',
            'user/two-factor-qr-code',
            'user/two-factor-secret-key',
            'user/two-factor-recovery-codes',
            'user/confirm-password',
            'force-change-password',
            'logout',
        ]);
    }
}
