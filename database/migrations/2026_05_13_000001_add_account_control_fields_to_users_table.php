<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'account_expires_at')) {
                $table->timestamp('account_expires_at')->nullable()->after('user_status');
            }

            if (! Schema::hasColumn('users', 'deactivation_reason')) {
                $table->text('deactivation_reason')->nullable()->after('account_expires_at');
            }

            if (! Schema::hasColumn('users', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable()->after('deactivation_reason');
            }

            if (! Schema::hasColumn('users', 'deactivated_by')) {
                $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('password_changed_time');
            }

            if (! Schema::hasColumn('users', 'last_logout_at')) {
                $table->timestamp('last_logout_at')->nullable()->after('last_login_at');
            }

            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('last_logout_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deactivated_by')) {
                $table->dropConstrainedForeignId('deactivated_by');
            }

            foreach ([
                'account_expires_at',
                'deactivation_reason',
                'deactivated_at',
                'last_login_at',
                'last_logout_at',
                'last_seen_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
