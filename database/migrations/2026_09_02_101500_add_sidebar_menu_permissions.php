<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Menu permissions control sidebar visibility only.
     *
     * Existing route/controller permissions continue to protect the actual pages.
     */
    private array $menuPermissions = [
        'dashboard.menu',
        'assets.menu',
        'asset-qrcodes.menu',
        'users.menu',
        'app-qr-links.menu',

        'roles-permissions.menu',
        'roles.menu',
        'permissions.menu',

        'security-audit.menu',
        'login-tracker.menu',
        'activity-tracker.menu',
        'audit-logs.menu',
        'notifications.menu',
        'asset-lifecycle.menu',

        'categories.menu',
        'organizations.menu',
        'units.menu',
        'suppliers.menu',
        'maintenance-providers.menu',
        'asset-names.menu',
        'asset-sources.menu',

        'approval-workflow.menu',
        'asset-request-approval.menu',
        'asset-transfer-approval.menu',
        'maintenance-request-approval.menu',

        'active-assets.menu',
        'obsolete-assets.menu',
        'disposed-assets.menu',
        'under-maintenance-assets.menu',
        'assets-inventory-by-office.menu',
        'yearly-purchased-assets.menu',
        'assets-count-by-year.menu',
        'assets-depreciation-report.menu',
        'maintenance-cost-summary.menu',

        'maintenance-requests.menu',
        'asset-requests.menu',
        'asset-transfers.menu',
        'accounts-payment.menu',

        'advanced-analytics.menu',
        'analytics-dashboard.menu',
        'asset-distribution-count.menu',
        'data-quality-index.menu',
        'maintenance-analytics.menu',
        'lifecycle-risk-analytics.menu',
        'warranty-end-of-life.menu',
        'implementation-evidence.menu',

        'advanced-reports.menu',
        'recycle-bin.menu',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $createdPermissions = collect($this->menuPermissions)
            ->map(function (string $name): Permission {
                $permission = Permission::withTrashed()->firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);

                if (method_exists($permission, 'trashed') && $permission->trashed()) {
                    $permission->restore();
                }

                return $permission;
            });

        /*
         * Preserve administrator access when the new menu permissions are added.
         * Other roles can be granted/revoked individually from Roles & Permissions.
         */
        Role::withTrashed()
            ->whereIn('name', ['Super Admin', 'Admin'])
            ->where('guard_name', 'web')
            ->get()
            ->each(function (Role $role) use ($createdPermissions): void {
                if (method_exists($role, 'trashed') && $role->trashed()) {
                    return;
                }

                $role->givePermissionTo($createdPermissions);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::withTrashed()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->menuPermissions)
            ->get()
            ->each(function (Permission $permission): void {
                $permission->forceDelete();
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
