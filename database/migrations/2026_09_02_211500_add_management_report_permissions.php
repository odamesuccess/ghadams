<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'management-reports.menu',
        'management-reports.asset-purchases.menu',
        'management-reports.maintenance.menu',
        'management-reports.annual-assets.menu',

        'management-reports.view',
        'management-reports.asset-purchases.view',
        'management-reports.maintenance.view',
        'management-reports.annual-assets.view',
        'management-reports.pdf',
    ];

    private array $requiredRoles = [
        'Head of Account',
        'Head of Administration',
        'Head of Audit',
        'Head of Estate',
        'Head of Inventory',
        'Super Admin',
    ];

    private array $roleAliases = [
        'Head of Accounts',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $createdPermissions = collect($this->permissions)
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
         * The six roles requested for management reporting are guaranteed to
         * exist and receive the complete report permission set.
         */
        foreach ($this->requiredRoles as $roleName) {
            $role = Role::withTrashed()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if (method_exists($role, 'trashed') && $role->trashed()) {
                $role->restore();
            }

            $role->givePermissionTo($createdPermissions);
        }

        /*
         * If an installation already uses the plural "Head of Accounts"
         * naming, grant the same permissions without creating that alias.
         */
        Role::query()
            ->whereIn('name', $this->roleAliases)
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($createdPermissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::withTrashed()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->get()
            ->each(function (Permission $permission): void {
                $permission->forceDelete();
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
