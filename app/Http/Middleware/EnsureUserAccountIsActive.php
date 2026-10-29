<?php

namespace App\Http\Middleware;

use App\Services\LoginTrackerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->isActive() || $user->isExpired())) {
            LoginTrackerService::record(
                $request,
                $user->isExpired() ? 'expired_blocked' : 'inactive_blocked',
                $user,
                $user->email,
                $user->isExpired()
                    ? 'User session blocked because account has expired.'
                    : 'User session blocked because account is inactive.'
            );

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Your account is inactive or has expired. Please contact the administrator.',
                ]);
        }

        if ($user) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
