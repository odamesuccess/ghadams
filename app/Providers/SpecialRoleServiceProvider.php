<?php

namespace App\Providers;

use App\Http\Middleware\EnforceDashboardDestination;
use App\Http\Middleware\EnforceSingleRoleAssignment;
use App\Http\Middleware\ShareSpecialRoleLanding;
use Illuminate\Support\ServiceProvider;

class SpecialRoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Laravel\Fortify\Contracts\LoginResponse::class, \App\Http\Responses\RoleAwareLoginResponse::class);
        $this->app->singleton(\Laravel\Fortify\Contracts\TwoFactorLoginResponse::class, \App\Http\Responses\RoleAwareTwoFactorLoginResponse::class);
        //
    }

    public function boot(): void
    {
        /*
         * The existing Fortify + mandatory 2FA implementation
         * remains unchanged.
         */
        $this->app->booted(
            function (): void {
                $router =
                    $this->app['router'];

                $router
                    ->pushMiddlewareToGroup(
                        'web',
                        EnforceSingleRoleAssignment::class
                    );

                $router
                    ->pushMiddlewareToGroup(
                        'web',
                        ShareSpecialRoleLanding::class
                    );

                $router
                    ->pushMiddlewareToGroup(
                        'web',
                        EnforceDashboardDestination::class
                    );
            }
        );
    }
}