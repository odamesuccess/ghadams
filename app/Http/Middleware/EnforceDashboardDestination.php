<?php

namespace App\Http\Middleware;

use App\Services\RoleLandingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDashboardDestination
{
    public function __construct(private readonly RoleLandingService $landing) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$this->landing->isSpecialRole($user)) return $next($request);
        // Authentication setup and profile/settings flows retain their own protections.
        $route = $request->route();
        if (!$route || !$this->landing->isDashboardRoute($route->getName(), $route->uri())) return $next($request);
        if ($this->landing->canAccessDashboard($user, $route->getName())) return $next($request);
        $destination = $this->landing->destinationUrl($user);
        if ($destination === null) {
            abort(403, 'This special role has no permitted landing page. Ask an administrator to assign a page permission.');
        }
        return redirect()->to($destination);
    }
}
