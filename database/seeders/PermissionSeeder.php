<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $modules = [
            'users',
            'roles',
            'permissions',
            'organizations',
            'units',
            'locations',
            'assetnames',
            'assetconditions',
            'assetlocations',
            'assetsources',
            'sources',
            'suppliers',
            'maintenanceproviders',
            'assets',
            'assetqrcodes',
            'links',
            'reports',
            'depreciationreports',
            'login-trackers',
            'activity-trackers',
            'audit-logs',
            'notifications',
            'asset-lifecycle',
            'approval-requests',
            'asset-requests',
            'asset-transfers',
            'maintenance-requests',
            'settings',
            'trash',
        ];

        $permissions = [
            'dashboard.view',
            'view.dashboard',
        ];

        foreach ($modules as $module) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                // Existing frontend style, e.g. users.view
                $permissions[] = "{$module}.{$action}";

                // Professional CRUD style, e.g. view.users
                $permissions[] = "{$action}.{$module}";
            }
        }

        $permissions = array_merge($permissions, [
            // Users / account control
            'users.activate',
            'users.deactivate',
            'users.expire',
            'users.extend-expiry',
            'users.reset-password',
            'activate.users',
            'deactivate.users',
            'expire.users',
            'extend-user-expiry.users',
            'reset-password.users',

            // Assets
            'assets.assign',
            'assets.transfer',
            'assets.dispose',
            'assets.restore',
            'assets.print',
            'assets.export',
            'assign.assets',
            'transfer.assets',
            'dispose.assets',
            'restore.assets',
            'print.assets',
            'export.assets',

            // QR / links
            'assetqrcodes.generate',
            'assetqrcodes.print',
            'assetqrcodes.scan',
            'generate.qr-codes',
            'print.qr-codes',
            'scan.qr-codes',
            'links.show',
            'links.qranalytic',
            'view.links',

            // Reports / exports
            'reports.export',
            'reports.print',
            'export.reports',
            'print.reports',
            'depreciationreports.export',
            'depreciationreports.print',
            'export.depreciation-reports',
            'print.depreciation-reports',

            // Trackers
            'login-trackers.export',
            'activity-trackers.export',
            'export.login-trackers',
            'export.activity-trackers',

            // Audit-style module aliases
            'audit-logs.view',
            'audit-logs.delete',
            'audit-logs.export',
            'view.audit-logs',
            'delete.audit-logs',
            'export.audit-logs',

            // Notifications
            'notifications.mark-read',
            'notifications.clear',
            'mark-read.notifications',
            'clear.notifications',


            // Recycle bin / soft deletes
            'trash.view',
            'trash.restore',
            'trash.force-delete',
            'view.trash',
            'restore.trash',
            'force-delete.trash',

            // Asset lifecycle / approval / maintenance workflows
            'asset-lifecycle.create',
            'asset-lifecycle.edit',
            'asset-lifecycle.delete',
            'create.asset-lifecycle',
            'edit.asset-lifecycle',
            'delete.asset-lifecycle',
            'approval-requests.approve',
            'approval-requests.reject',
            'asset-requests.approve',
            'asset-requests.reject',
            'asset-requests.supply',
            'asset-requests.cancel',
            'asset-transfers.approve',
            'asset-transfers.reject',
            'asset-transfers.complete',
            'asset-transfers.cancel',
            'approve.asset-requests',
            'reject.asset-requests',
            'supply.asset-requests',
            'cancel.asset-requests',
            'approve.asset-transfers',
            'reject.asset-transfers',
            'complete.asset-transfers',
            'cancel.asset-transfers',
            'approve.approval-requests',
            'reject.approval-requests',
            'maintenance-requests.approve',
            'maintenance-requests.reject',
            'maintenance-requests.complete',
            'approve.maintenance-requests',
            'reject.maintenance-requests',
            'complete.maintenance-requests',
        ]);

        foreach (array_unique($permissions) as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (['Super Admin', 'Admin'] as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(Permission::all());
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
