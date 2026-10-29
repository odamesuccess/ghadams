<?php

namespace App\Providers;

use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetLifecycleHistory;
use App\Models\AssetLocation;
use App\Models\AssetName;
use App\Models\AssetScanLog;
use App\Models\AssetSource;
use App\Models\Link;
use App\Models\LinkScan;
use App\Models\Location;
use App\Models\LoginTracker;
use App\Models\MaintenanceProvider;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\PasswordHistory;
use App\Models\Permission;
use App\Models\QrReprintHistory;
use App\Models\Role;
use App\Models\Source;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Observers\ActivityTrackerObserver;
use App\Services\LoginTrackerService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->shareInertiaData();
        $this->registerActivityObservers();
        $this->registerAuthEventTrackers();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function shareInertiaData(): void
    {
        Inertia::share([
            'auth' => fn () => [
                'user' => Auth::check()
                    ? Auth::user()->fresh([
                        'roles:id,name',
                        'unit:id,name',
                    ])
                    : null,

                'permissions' => Auth::check()
                    ? Auth::user()
                        ->getAllPermissions()
                        ->pluck('name')
                        ->values()
                    : [],
            ],
        ]);
    }

    protected function registerActivityObservers(): void
    {
        $models = [
            Asset::class,
            AssetCondition::class,
            AssetLifecycleHistory::class,
            AssetLocation::class,
            AssetName::class,
            AssetScanLog::class,
            AssetSource::class,
            ApprovalRequest::class,
            Link::class,
            LinkScan::class,
            Location::class,
            LoginTracker::class,
            MaintenanceProvider::class,
            MaintenanceRequest::class,
            Organization::class,
            PasswordHistory::class,
            Permission::class,
            QrReprintHistory::class,
            Role::class,
            Source::class,
            Supplier::class,
            Unit::class,
            User::class,
        ];

        foreach ($models as $model) {
            if (class_exists($model)) {
                $model::observe(ActivityTrackerObserver::class);
            }
        }
    }

    protected function registerAuthEventTrackers(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Password Reset Link Tracking
        |--------------------------------------------------------------------------
        | This tracks only password reset link notifications.
        | It does not create login_success records.
        */

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if (! $event->notifiable instanceof User) {
                return;
            }

            if (! $event->notification instanceof ResetPassword) {
                return;
            }

            LoginTrackerService::record(
                request(),
                'password_reset_link_sent',
                $event->notifiable,
                $event->notifiable->email,
                'Password reset link was sent to user email.'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Logout Tracking
        |--------------------------------------------------------------------------
        | This updates the latest open login_success record with logout_at and
        | session_duration_seconds through LoginTrackerService.
        |
        | Important:
        | Do not record login_success here. Login success should remain only in
        | FortifyServiceProvider to prevent duplicate login rows.
        */

        Event::listen(Logout::class, function (Logout $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill([
                'last_logout_at' => now(),
            ])->saveQuietly();

            LoginTrackerService::record(
                request(),
                'logout',
                $event->user,
                $event->user->email,
                'User logged out.'
            );
        });
    }
}
