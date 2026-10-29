<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class UserSessionMonitorController extends Controller
{
    private const ONLINE_MINUTES = 5;
    private const IDLE_MINUTES = 15;

    public function index(Request $request): Response
    {
        $this->authorizeMonitor($request);

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => (string) $request->input('status', ''),
            'role' => (string) $request->input('role', ''),
            'unit' => (string) $request->input('unit', ''),
            'country' => (string) $request->input('country', ''),
        ];

        $sessionRows = $this->sessionRows();
        $sessionsByUser = $sessionRows->groupBy('user_id');

        $query = User::query()
            ->with([
                'unit:id,name',
                'roles:id,name',
            ])
            ->select([
                'id',
                'name',
                'email',
                'unit_id',
                'user_status',
                'profile_image',
                'last_login_at',
                'last_logout_at',
                'last_seen_at',
                'last_login_ip',
                'last_login_city',
                'last_login_region',
                'last_login_country',
            ])
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('last_login_ip', 'like', "%{$search}%")
                        ->orWhere('last_login_city', 'like', "%{$search}%")
                        ->orWhere('last_login_country', 'like', "%{$search}%")
                        ->orWhereHas('unit', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('roles', fn ($r) => $r->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['role'], fn ($q, $role) =>
                $q->whereHas('roles', fn ($r) => $r->where('name', $role))
            )
            ->when($filters['unit'], fn ($q, $unit) =>
                $q->whereHas('unit', fn ($u) => $u->where('name', $unit))
            )
            ->when($filters['country'], fn ($q, $country) =>
                $q->where('last_login_country', $country)
            )
            ->orderByRaw('last_seen_at IS NULL')
            ->orderByDesc('last_seen_at');

        $users = $query->get()
            ->map(fn (User $user) => $this->mapUser($user, $sessionsByUser->get($user->id, collect())))
            ->when($filters['status'], fn ($items, $status) =>
                $items->where('presence', $status)->values()
            );

        $summaryUsers = User::query()
            ->select(['id', 'last_seen_at'])
            ->get();

        $summary = [
            'online' => $summaryUsers->filter(fn ($u) => $this->presenceFrom($u->last_seen_at) === 'online')->count(),
            'idle' => $summaryUsers->filter(fn ($u) => $this->presenceFrom($u->last_seen_at) === 'idle')->count(),
            'offline' => $summaryUsers->filter(fn ($u) => $this->presenceFrom($u->last_seen_at) === 'offline')->count(),
            'active_today' => $summaryUsers->filter(fn ($u) => $u->last_seen_at?->isToday())->count(),
        ];

        return Inertia::render('UserSessionMonitor/Index', [
            'monitorRows' => $users->values(),
            'summary' => $summary,
            'filters' => $filters,
            'roles' => DB::table('roles')->whereNull('deleted_at')->orderBy('name')->pluck('name')->values(),
            'units' => DB::table('units')->whereNull('deleted_at')->orderBy('name')->pluck('name')->values(),
            'countries' => User::query()
                ->whereNotNull('last_login_country')
                ->where('last_login_country', '!=', '')
                ->distinct()
                ->orderBy('last_login_country')
                ->pluck('last_login_country')
                ->values(),
            'presenceRules' => [
                'online_minutes' => self::ONLINE_MINUTES,
                'idle_minutes' => self::IDLE_MINUTES,
            ],
            'refreshedAt' => now()->toIso8601String(),
        ]);
    }

    private function authorizeMonitor(Request $request): void
    {
        $user = $request->user();
        $isSuperAdmin = $user?->roles?->contains(
            fn ($role) => strtolower(trim((string) $role->name)) === 'super admin'
        ) ?? false;

        abort_unless(
            $isSuperAdmin
            || $user?->can('monitor-users-online.view')
            || $user?->can('monitor-users-online.menu'),
            403
        );
    }

    private function sessionRows()
    {
        if (! Schema::hasTable('sessions')) {
            return collect();
        }

        $columns = [
            'id',
            'user_id',
            'ip_address',
            'last_activity',
        ];

        foreach (['current_route', 'current_url', 'login_at', 'city', 'region', 'country'] as $column) {
            if (Schema::hasColumn('sessions', $column)) {
                $columns[] = $column;
            }
        }

        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->select($columns)
            ->orderByDesc('last_activity')
            ->get();
    }

    private function mapUser(User $user, $sessions): array
    {
        $latest = $sessions->sortByDesc('last_activity')->first();
        $activeCutoff = now()->subMinutes(self::IDLE_MINUTES)->timestamp;
        $activeSessions = $sessions->where('last_activity', '>=', $activeCutoff)->count();

        $currentRoute = $latest->current_route ?? null;
        $currentUrl = $latest->current_url ?? null;

        $country = $latest->country ?? $user->last_login_country ?? null;
        $city = $latest->city ?? $user->last_login_city ?? null;
        $region = $latest->region ?? $user->last_login_region ?? null;
        $ip = $latest->ip_address ?? $user->last_login_ip ?? null;

        $securityFlags = [];

        if ($activeSessions > 1) {
            $securityFlags[] = 'Multiple active sessions';
        }

        if ($country && ! in_array(strtolower($country), ['ghana', 'local', 'unknown'], true)) {
            $securityFlags[] = 'Foreign login';
        }

        $loginAt = isset($latest->login_at) && $latest->login_at
            ? Carbon::parse($latest->login_at)
            : $user->last_login_at;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_image' => $user->profile_image,
            'role' => $user->roles->pluck('name')->join(', ') ?: 'No role',
            'unit' => $user->unit?->name ?? 'No unit',
            'presence' => $this->presenceFrom($user->last_seen_at),
            'current_page' => $this->pageLabel($currentRoute, $currentUrl),
            'current_url' => $currentUrl,
            'login_at' => $loginAt?->toIso8601String(),
            'last_activity_at' => $user->last_seen_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_logout_at' => $user->last_logout_at?->toIso8601String(),
            'session_duration_seconds' => $loginAt ? max(0, $loginAt->diffInSeconds(now())) : null,
            'ip_address' => $ip,
            'city' => $city,
            'region' => $region,
            'country' => $country,
            'active_sessions' => $activeSessions,
            'security_flags' => $securityFlags,
        ];
    }

    private function presenceFrom($lastSeen): string
    {
        if (! $lastSeen) {
            return 'offline';
        }

        $seen = $lastSeen instanceof Carbon
            ? $lastSeen
            : Carbon::parse($lastSeen);

        if ($seen->gte(now()->subMinutes(self::ONLINE_MINUTES))) {
            return 'online';
        }

        if ($seen->gte(now()->subMinutes(self::IDLE_MINUTES))) {
            return 'idle';
        }

        return 'offline';
    }

    private function pageLabel(?string $route, ?string $url): string
    {
        if ($route) {
            return str($route)
                ->replace(['.', '-', '_'], ' ')
                ->title()
                ->toString();
        }

        if ($url && $url !== '/') {
            return str($url)
                ->trim('/')
                ->replace(['/', '-', '_'], ' ')
                ->title()
                ->toString();
        }

        return '—';
    }
}
