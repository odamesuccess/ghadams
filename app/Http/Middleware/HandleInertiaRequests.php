<?php

namespace App\Http\Middleware;

use App\Models\SystemNotification;
use App\Services\DemoData\SyntheticDatasetWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props for all Inertia responses.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [

            /* ===========================
               APP
            ============================ */
            'app' => [
                'name' => config('app.name'),
            ],

            /* ===========================
               AUTH (USER + PERMISSIONS)
            ============================ */
            'auth' => [
                'user' => $user
                    ? $user->load('unit', 'roles')
                    : null,

                'permissions' => $user
                    ? $user
                        ->getAllPermissions()
                        ->pluck('name')
                        ->values()
                    : [],
            ],

            /* ===========================
               GLOBAL NOTIFICATIONS
            ============================ */
            'notificationUnreadCount' => fn () => $user
                ? SystemNotification::query()
                    ->where(function ($query) use ($user): void {
                        $query
                            ->whereNull('user_id')
                            ->orWhere('user_id', $user->id);
                    })
                    ->whereNull('read_at')
                    ->count()
                : 0,

            'notifications' => fn () => [
                'unread_count' => $user
                    ? SystemNotification::query()
                        ->where(function ($query) use ($user): void {
                            $query
                                ->whereNull('user_id')
                                ->orWhere('user_id', $user->id);
                        })
                        ->whereNull('read_at')
                        ->count()
                    : 0,
            ],

            /* ===========================
               SYNTHETIC DEMO DATA INDICATOR (admins only)
            ============================ */
            'demoDataset' => fn () => $this->demoDataset($user),

            /* ===========================
               FLASH MESSAGES
            ============================ */
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],

            /* ===========================
               UI STATE
            ============================ */
            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
        ]);
    }

    /**
     * Tells administrators that the register contains synthetic demo rows.
     */
    private function demoDataset($user): ?array
    {
        if (! $user || ! $user->hasAnyRole(['Admin', 'Super Admin'])
            || ! Schema::hasTable(SyntheticDatasetWriter::REGISTRY)) {
            return null;
        }

        $assets = DB::table(SyntheticDatasetWriter::REGISTRY)
            ->where('dataset', config('demo_dataset.dataset'))
            ->where('record_type', 'asset')
            ->count();

        return $assets > 0
            ? ['label' => config('demo_dataset.label'), 'assets' => $assets]
            : null;
    }
}
