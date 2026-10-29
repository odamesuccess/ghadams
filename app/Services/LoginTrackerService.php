<?php

namespace App\Services;

use App\Models\LoginTracker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginTrackerService
{
    private const LOGIN_DEDUP_WINDOW_SECONDS = 10;

    public static function record(
        Request $request,
        string $event,
        ?User $user = null,
        ?string $email = null,
        ?string $description = null
    ): void {
        try {
            if ($event === 'logout') {
                self::recordLogout($request, $user, $email, $description);
                return;
            }

            if ($event === 'login_success') {
                self::recordLoginSuccess($request, $user, $email, $description);
                return;
            }

            LoginTracker::create([
                'user_id' => $user?->id,
                'email' => $email ?? $user?->email,
                'event' => $event,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'browser' => self::browser($request->userAgent()),
                'platform' => self::platform($request->userAgent()),
                'device' => self::device($request->userAgent()),
                'login_at' => null,
                'logout_at' => null,
                'session_duration_seconds' => null,
                'description' => $description,
            ]);

            self::recordSecurityAuditAndNotification($request, $event, $user, $email, $description);
        } catch (\Throwable $e) {
            Log::warning('Login tracker failed: '.$e->getMessage());
        }
    }

    private static function recordLoginSuccess(
        Request $request,
        ?User $user = null,
        ?string $email = null,
        ?string $description = null
    ): void {
        $loginAt = now();
        $emailValue = $email ?? $user?->email;
        $userAgent = $request->userAgent();

        /*
        |--------------------------------------------------------------------------
        | Duplicate Login Protection
        |--------------------------------------------------------------------------
        | Fortify authentication can be touched more than once during the same
        | browser login flow. This guard keeps only one login_success row for the
        | same user/email/IP/browser within a short window.
        */
        $duplicate = LoginTracker::query()
            ->where('event', 'login_success')
            ->whereNull('logout_at')
            ->where('created_at', '>=', $loginAt->copy()->subSeconds(self::LOGIN_DEDUP_WINDOW_SECONDS))
            ->where('ip_address', $request->ip())
            ->where('user_agent', $userAgent)
            ->when($user?->id, fn ($query) => $query->where('user_id', $user->id))
            ->when(! $user?->id && $emailValue, fn ($query) => $query->where('email', $emailValue))
            ->latest('created_at')
            ->first();

        if ($duplicate) {
            $duplicateLoginAt = $duplicate->login_at ?? $duplicate->created_at;

            $request->session()->put('login_tracker_id', $duplicate->id);
            $request->session()->put('login_tracker_login_at', $duplicateLoginAt?->toDateTimeString());

            return;
        }

        $tracker = LoginTracker::create([
            'user_id' => $user?->id,
            'email' => $emailValue,
            'event' => 'login_success',
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'browser' => self::browser($userAgent),
            'platform' => self::platform($userAgent),
            'device' => self::device($userAgent),
            'login_at' => $loginAt,
            'logout_at' => null,
            'session_duration_seconds' => null,
            'description' => $description,
        ]);

        $request->session()->put('login_tracker_id', $tracker->id);
        $request->session()->put('login_tracker_login_at', $loginAt->toDateTimeString());

        self::recordSecurityAuditAndNotification($request, 'login_success', $user, $emailValue, $description);
    }

    private static function recordLogout(
        Request $request,
        ?User $user = null,
        ?string $email = null,
        ?string $description = null
    ): void {
        $logoutAt = now();
        $emailValue = $email ?? $user?->email;
        $trackerId = $request->session()->get('login_tracker_id');

        $tracker = null;

        if ($trackerId) {
            $tracker = LoginTracker::query()
                ->whereKey($trackerId)
                ->where('event', 'login_success')
                ->whereNull('logout_at')
                ->first();
        }

        if (! $tracker) {
            $tracker = LoginTracker::query()
                ->when($user?->id, fn ($query) => $query->where('user_id', $user->id))
                ->when(! $user?->id && $emailValue, fn ($query) => $query->where('email', $emailValue))
                ->where('event', 'login_success')
                ->whereNull('logout_at')
                ->latest('login_at')
                ->latest('created_at')
                ->first();
        }

        if ($tracker) {
            $loginAt = $tracker->login_at ?? $tracker->created_at;

            $tracker->forceFill([
                'logout_at' => $logoutAt,
                'session_duration_seconds' => $loginAt
                    ? $loginAt->diffInSeconds($logoutAt)
                    : null,
                'description' => trim(($tracker->description ? $tracker->description.' ' : '').($description ?? 'User logged out.')),
            ])->saveQuietly();
        }

        $request->session()->forget([
            'login_tracker_id',
            'login_tracker_login_at',
        ]);

        self::recordSecurityAuditAndNotification($request, 'logout', $user, $emailValue, $description ?? 'User logged out.');
    }

    private static function recordSecurityAuditAndNotification(
        Request $request,
        string $event,
        ?User $user = null,
        ?string $email = null,
        ?string $description = null
    ): void {
        AuditLogService::record(
            $request,
            'Authentication',
            $event,
            str_replace('_', ' ', ucfirst($event)),
            $description,
            $user,
            null,
            ['email' => $email ?? $user?->email, 'event' => $event],
            $user?->id
        );

        if (in_array($event, ['login_success', 'logout', 'password_reset_link_sent', 'password_reset_completed', 'inactive_blocked', 'expired_blocked'], true)) {
            SystemNotificationService::notifyAdmins(
                type: 'security',
                title: str_replace('_', ' ', ucfirst($event)),
                message: $description,
                actorId: $user?->id,
                modelType: $user ? get_class($user) : null,
                modelId: $user?->id,
                data: ['email' => $email ?? $user?->email, 'event' => $event],
                duplicateWindowSeconds: 15
            );
        }
    }

    private static function browser(?string $agent): ?string
    {
        if (! $agent) {
            return null;
        }

        return match (true) {
            str_contains($agent, 'Edg') => 'Microsoft Edge',
            str_contains($agent, 'OPR') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome') => 'Google Chrome',
            str_contains($agent, 'Firefox') => 'Mozilla Firefox',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Unknown Browser',
        };
    }

    private static function platform(?string $agent): ?string
    {
        if (! $agent) {
            return null;
        }

        return match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Mac') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown Platform',
        };
    }

    private static function device(?string $agent): ?string
    {
        if (! $agent) {
            return null;
        }

        return match (true) {
            str_contains($agent, 'iPad') || str_contains($agent, 'Tablet') => 'Tablet',
            str_contains($agent, 'Mobile') || str_contains($agent, 'Android') || str_contains($agent, 'iPhone') => 'Mobile',
            default => 'Desktop',
        };
    }
}
