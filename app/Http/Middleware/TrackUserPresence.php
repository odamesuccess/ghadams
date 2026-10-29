<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if (! $user || ! $request->hasSession()) {
            return $response;
        }

        if (! Schema::hasTable('sessions')) {
            return $response;
        }

        $sessionId = $request->session()->getId();
        $ip = $request->ip();

        $currentRoute = $request->route()?->getName();
        $currentUrl = '/'.ltrim($request->path(), '/');

        $updates = [];

        if (Schema::hasColumn('sessions', 'current_route')) {
            $updates['current_route'] = $currentRoute;
        }

        if (Schema::hasColumn('sessions', 'current_url')) {
            $updates['current_url'] = $currentUrl;
        }

        if (Schema::hasColumn('sessions', 'login_at')) {
            $existingLoginAt = DB::table('sessions')
                ->where('id', $sessionId)
                ->value('login_at');

            if (! $existingLoginAt) {
                $updates['login_at'] = now();
            }
        }

        $needsLocation = false;

        if (Schema::hasColumn('sessions', 'country')) {
            $country = DB::table('sessions')
                ->where('id', $sessionId)
                ->value('country');

            $needsLocation = blank($country);
        }

        if ($needsLocation) {
            [$city, $region, $country] = $this->resolveLocation($ip);

            if (Schema::hasColumn('sessions', 'city')) {
                $updates['city'] = $city;
            }

            if (Schema::hasColumn('sessions', 'region')) {
                $updates['region'] = $region;
            }

            if (Schema::hasColumn('sessions', 'country')) {
                $updates['country'] = $country;
            }

            $userUpdates = [];

            if (Schema::hasColumn('users', 'last_login_ip')) {
                $userUpdates['last_login_ip'] = $ip;
            }

            if (Schema::hasColumn('users', 'last_login_city')) {
                $userUpdates['last_login_city'] = $city;
            }

            if (Schema::hasColumn('users', 'last_login_region')) {
                $userUpdates['last_login_region'] = $region;
            }

            if (Schema::hasColumn('users', 'last_login_country')) {
                $userUpdates['last_login_country'] = $country;
            }

            if ($userUpdates) {
                $user->forceFill($userUpdates)->saveQuietly();
            }
        }

        if ($updates) {
            DB::table('sessions')
                ->where('id', $sessionId)
                ->update($updates);
        }

        return $response;
    }

    private function resolveLocation(?string $ip): array
    {
        if (! $ip || $this->isLocalIp($ip)) {
            return ['Local', 'Local', 'Local'];
        }

        try {
            $position = Location::get($ip);

            if (! $position) {
                return ['Unknown', 'Unknown', 'Unknown'];
            }

            return [
                $position->cityName ?: 'Unknown',
                $position->regionName ?: 'Unknown',
                $position->countryName ?: 'Unknown',
            ];
        } catch (Throwable) {
            return ['Unknown', 'Unknown', 'Unknown'];
        }
    }

    private function isLocalIp(string $ip): bool
    {
        return $ip === '127.0.0.1'
            || $ip === '::1'
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip) === 1;
    }
}
