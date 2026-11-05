<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * permission => roles that receive it
     */
    private array $grants = [
        'assets.research' => ['Super Admin', 'Head of Inventory', 'Inventory Offer'],
        'assets.review-suggestions' => ['Super Admin', 'Head of Inventory'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->grants as $name => $roleNames) {
            $permission = Permission::withTrashed()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            if (method_exists($permission, 'trashed') && $permission->trashed()) {
                $permission->restore();
            }

            // Only grant to roles that already exist on this installation.
            Role::query()
                ->whereIn('name', $roleNames)
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::withTrashed()
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys($this->grants))
            ->get()
            ->each(fn (Permission $permission) => $permission->forceDelete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
