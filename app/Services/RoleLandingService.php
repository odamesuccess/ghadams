<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleLandingService
{
    public const SPECIAL_ROLE_NAME = 'Special Role';

    public function isSpecialDefinition(Role $role): bool
    {
        return (bool) $role->is_special || $role->name === self::SPECIAL_ROLE_NAME;
    }

    public function singleRole(User $user): ?Role
    {
        $roles = $user->relationLoaded('roles') ? $user->roles : $user->roles()->with('permissions')->get();
        return $roles->count() === 1 ? $roles->first() : null;
    }

    public function specialRole(): ?Role
    {
        return Role::query()->with('permissions')->where('name', self::SPECIAL_ROLE_NAME)->first();
    }

    private function specialRoles(User $user)
    {
        $roles = $user->relationLoaded('roles') ? $user->roles : $user->roles()->with('permissions')->get();
        return $roles->filter(fn ($role) => $this->isSpecialDefinition($role))->sortBy('id');
    }

    public function isSpecialRole(User $user): bool
    {
        // Any special assignment enforces the restriction, even on legacy multi-role users.
        return $this->specialRoles($user)->isNotEmpty();
    }

    public function isDashboardRoute(?string $name, string $uri): bool
    {
        return in_array($name, ['dashboard', 'home', 'advanced-analytics.dashboard'], true)
            || str_contains(strtolower((string) $name), 'dashboard')
            || in_array(trim($uri, '/'), ['', 'dashboard', 'advanced-dashboard', 'advanced-analytics'], true);
    }

    /** Static, permission-protected GET pages; each middleware group is an AND requirement. */
    public function landingOptions(?Role $role = null): array
    {
        $options = [];
        foreach (Route::getRoutes() as $route) {
            if (!$route instanceof LaravelRoute) continue;
            app(\App\Support\PageAccessCatalog::class)->apply($route);
            if (!$this->isLandingCandidate($route)) continue;
            $groups = $this->permissionGroups($route);
            if ($groups === []) continue;
            $options[] = [
                'route' => $route->getName(),
                'url' => '/'.ltrim($route->uri(), '/'),
                'label' => (config('page_access', [])[$route->getName()]['label'] ?? Str::headline(str_replace(['.', '-', '_'], ' ', $route->getName()))),
                'permissions' => array_values(array_unique(array_merge(...$groups))),
                'permission_groups' => $groups,
            ];
        }
        usort($options, fn ($a, $b) => strcmp($a['label'], $b['label']));
        if ($role !== null) {
            $assigned = $this->rolePermissions($role);
            $options = array_values(array_filter($options, fn ($option) => $this->optionAllowed($option, $assigned)));
        }
        return $options;
    }

    public function landingAllowedForPermissions(?string $name, array $permissions): bool
    {
        foreach ($this->landingOptions() as $option) {
            if ($option['route'] === $name) return $this->optionAllowed($option, $permissions);
        }
        return false;
    }

    private function optionAllowed(array $option, array $permissions): bool
    {
        foreach ($option['permission_groups'] as $group) {
            if (array_intersect($group, $permissions) === []) return false;
        }
        return $option['permission_groups'] !== [];
    }

    private function rolePermissions(Role $role): array
    {
        if (!$role->relationLoaded('permissions')) $role->load('permissions');
        return $role->permissions->pluck('name')->all();
    }

    public function roleCanUseLanding(Role $role, ?string $routeName): bool
    {
        return $this->isSpecialDefinition($role)
            && $this->landingAllowedForPermissions($routeName, $this->rolePermissions($role));
    }

    public function landingRouteName(User $user): ?string
    {
        foreach ($this->specialRoles($user) as $role) {
            if ($this->roleCanUseLanding($role, $role->landing_route)) return $role->landing_route;
            // If a permission was removed, use another page belonging to the special role.
            $options = $this->landingOptions($role);
            if ($options !== []) return $options[0]['route'];
        }
        return null;
    }

    public function destinationUrl(User $user): ?string
    {
        $name = $this->landingRouteName($user);
        if ($name === null) return null;
        foreach ($this->landingOptions() as $option) {
            if ($option['route'] === $name) return $option['url'];
        }
        return null;
    }

    // Assigned dashboard permission takes precedence over the special-role flag.
    public function canAccessDashboard(User $user, ?string $routeName): bool
    {
        foreach ($this->specialRoles($user) as $role) {
            if ($this->roleCanUseLanding($role, $routeName)) return true;
        }
        return false;
    }

    public function payload(User $user): array
    {
        $special = $this->isSpecialRole($user);
        $assigned = [];
        foreach ($this->specialRoles($user) as $role) {
            $assigned = array_merge($assigned, $this->rolePermissions($role));
        }
        $actual = $user->getAllPermissions()->pluck('name')->all();
        $assigned = array_values(array_intersect(array_unique($assigned), $actual));
        $pagePermissions = $special ? $assigned : $actual;
        $pages = array_values(array_filter($this->landingOptions(), fn ($option) => $this->optionAllowed($option, $pagePermissions)));
        $menuPermissions = [];
        foreach ($pages as $page) {
            foreach ((config('page_access', [])[$page['route']]['permissions'] ?? []) as $permission) {
                if (str_ends_with($permission, '.menu')) $menuPermissions[] = $permission;
            }
        }
        return [
            'pages' => $pages,
            'menuPermissions' => array_values(array_unique($menuPermissions)),
            'dashboardPages' => $special ? array_values(array_filter($this->landingOptions(), fn ($option) => $this->isDashboardRoute($option['route'], $option['url']) && $this->canAccessDashboard($user, $option['route']))) : [],
            'active' => $special,
            'role' => $this->specialRoles($user)->first()?->name,
            'landingRoute' => $special ? $this->landingRouteName($user) : null,
            'landingUrl' => $special ? $this->destinationUrl($user) : null,
            'defaultUrl' => $special ? $this->destinationUrl($user) : '/dashboard',
        ];
    }

    private function isLandingCandidate(LaravelRoute $route): bool
    {
        $name = $route->getName();
        if (!$name || !in_array('GET', $route->methods(), true) || $route->parameterNames() !== []) return false;
        if ($name === 'home' || trim($route->uri(), '/') === '') return false;
        $blocked = '/(^|[.\/-])(login|logout|register|password|verification|two-factor|download|export|pdf|csv|print|preview|stream|poll|qr-code|secret-key|create|asset-search|latest|options|backfill-existing|qr-test|access-overview|my-maintenance-payments)([.\/-]|$)/i';
        if (preg_match($blocked, $name) || preg_match($blocked, $route->uri())) return false;
        foreach ($route->gatherMiddleware() as $middleware) {
            // A permission alone cannot authorize a route that also demands a specific role.
            if (is_string($middleware) && (str_starts_with($middleware, 'role:') || str_starts_with($middleware, 'role_or_permission:'))) return false;
        }
        return true;
    }

    public function permissionGroups(LaravelRoute $route): array
    {
        $groups = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (!is_string($middleware)) continue;
            if (str_starts_with($middleware, 'permission:') || str_starts_with($middleware, 'can:')) {
                $raw = explode(',', explode(':', $middleware, 2)[1])[0];
                $groups[] = array_values(array_filter(array_map('trim', explode('|', $raw))));
            }
        }
        return $groups;
    }
}
