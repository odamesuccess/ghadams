<?php
namespace App\Providers;
use App\Http\Controllers\PermissionGuideController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
class PermissionGuideServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!$this->app->routesAreCached()) {
            Route::get('/role-permission-guide', [PermissionGuideController::class, 'index'])
                ->middleware(['web', 'auth', 'force.password.change', 'account.active',
                    'permission:roles.view|view.roles|roles.create|create.roles|roles.edit|edit.roles'])
                ->name('roles.permission-guide');
        }
    }
}
