<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_trackers', function (Blueprint $table) {
            if (! Schema::hasColumn('login_trackers', 'login_at')) {
                $table->timestamp('login_at')->nullable()->after('device');
            }

            if (! Schema::hasColumn('login_trackers', 'logout_at')) {
                $table->timestamp('logout_at')->nullable()->after('login_at');
            }

            if (! Schema::hasColumn('login_trackers', 'session_duration_seconds')) {
                $table->unsignedInteger('session_duration_seconds')->nullable()->after('logout_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('login_trackers', function (Blueprint $table) {
            if (Schema::hasColumn('login_trackers', 'session_duration_seconds')) {
                $table->dropColumn('session_duration_seconds');
            }

            if (Schema::hasColumn('login_trackers', 'logout_at')) {
                $table->dropColumn('logout_at');
            }

            if (Schema::hasColumn('login_trackers', 'login_at')) {
                $table->dropColumn('login_at');
            }
        });
    }
};
