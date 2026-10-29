<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_scan_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_scan_logs', 'scanned_by')) {
                $table->foreignId('scanned_by')->nullable()->after('asset_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('asset_scan_logs', 'browser')) {
                $table->string('browser')->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('asset_scan_logs', 'platform')) {
                $table->string('platform')->nullable()->after('browser');
            }
            if (! Schema::hasColumn('asset_scan_logs', 'device')) {
                $table->string('device')->nullable()->after('platform');
            }
            if (! Schema::hasColumn('asset_scan_logs', 'scan_location')) {
                $table->string('scan_location')->nullable()->after('device');
            }
            if (! Schema::hasColumn('asset_scan_logs', 'is_unknown_location')) {
                $table->boolean('is_unknown_location')->default(false)->after('scan_location');
            }
            if (! Schema::hasColumn('asset_scan_logs', 'is_tamper_suspected')) {
                $table->boolean('is_tamper_suspected')->default(false)->after('is_unknown_location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_scan_logs', function (Blueprint $table) {
            if (Schema::hasColumn('asset_scan_logs', 'scanned_by')) {
                $table->dropConstrainedForeignId('scanned_by');
            }
            foreach (['is_tamper_suspected','is_unknown_location','scan_location','device','platform','browser'] as $column) {
                if (Schema::hasColumn('asset_scan_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
