<?php

namespace App\Http\Middleware;

use App\Services\RoleLandingService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareSpecialRoleLanding
{
    public function __construct(
        private readonly
        RoleLandingService $landing
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        Inertia::share(
            'specialRole',
            $user
                ? fn () =>
                    $this->landing
                        ->payload($user)
                : [
                    'active' => false,
                    'role' => null,
                    'landingRoute' => null,
                    'landingUrl' => null,
                    'defaultUrl' => '/dashboard',
                ]
        );

        return $next($request);
    }
}