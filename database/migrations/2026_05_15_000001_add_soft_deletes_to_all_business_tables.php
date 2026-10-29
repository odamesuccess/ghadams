<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom application tables that should support recycle-bin style deletes.
     * System queue/cache/session tables are intentionally excluded.
     */
    private array $tables = [
        'users',
        'password_histories',
        'organizations',
        'maintenance_providers',
        'units',
        'suppliers',
        'asset_names',
        'asset_conditions',
        'asset_locations',
        'assets',
        'asset_scan_logs',
        'asset_office_locations',
        'locations',
        'asset_sources',
        'sources',
        'links',
        'link_scans',
        'system_notifications',
        'login_trackers',
        'activity_trackers',
        'audit_logs',
        'asset_lifecycle_histories',
        'approval_requests',
        'maintenance_requests',
        'qr_reprint_histories',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'deleted_at')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'deleted_at')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
