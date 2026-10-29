<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['monitor-users-online.menu', 'monitor-users-online.view'] as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::query()
            ->whereRaw('LOWER(name) = ?', ['super admin'])
            ->first();

        if ($superAdmin) {
            $superAdmin->givePermissionTo([
                'monitor-users-online.menu',
                'monitor-users-online.view',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', [
                'monitor-users-online.menu',
                'monitor-users-online.view',
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
