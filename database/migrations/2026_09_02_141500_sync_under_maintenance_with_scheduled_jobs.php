<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('assets')
            || ! Schema::hasTable('maintenance_requests')
        ) {
            return;
        }

        DB::table('assets')
            ->where('status', 'under_maintenance')
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('maintenance_requests')
                    ->whereColumn('maintenance_requests.asset_id', 'assets.id')
                    ->whereNull('maintenance_requests.deleted_at')
                    ->whereNotNull('maintenance_requests.scheduled_date')
                    ->whereIn(
                        'maintenance_requests.status',
                        ['pending', 'approved', 'in_progress']
                    );
            })
            ->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);

        DB::table('assets')
            ->whereNotIn('status', ['obsolete', 'disposed'])
            ->whereExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('maintenance_requests')
                    ->whereColumn('maintenance_requests.asset_id', 'assets.id')
                    ->whereNull('maintenance_requests.deleted_at')
                    ->whereNotNull('maintenance_requests.scheduled_date')
                    ->whereIn(
                        'maintenance_requests.status',
                        ['pending', 'approved', 'in_progress']
                    );
            })
            ->update([
                'status' => 'under_maintenance',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally not reversed: the previous data may have been stale.
    }
};
