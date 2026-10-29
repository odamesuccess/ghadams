<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('sessions', 'current_route')) {
                    $table->string('current_route')->nullable()->after('user_agent');
                }

                if (! Schema::hasColumn('sessions', 'current_url')) {
                    $table->string('current_url', 1000)->nullable()->after('current_route');
                }

                if (! Schema::hasColumn('sessions', 'login_at')) {
                    $table->timestamp('login_at')->nullable()->after('current_url');
                }

                if (! Schema::hasColumn('sessions', 'city')) {
                    $table->string('city')->nullable()->after('login_at');
                }

                if (! Schema::hasColumn('sessions', 'region')) {
                    $table->string('region')->nullable()->after('city');
                }

                if (! Schema::hasColumn('sessions', 'country')) {
                    $table->string('country')->nullable()->after('region');
                }
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_seen_at');
            }

            if (! Schema::hasColumn('users', 'last_login_city')) {
                $table->string('last_login_city')->nullable()->after('last_login_ip');
            }

            if (! Schema::hasColumn('users', 'last_login_region')) {
                $table->string('last_login_region')->nullable()->after('last_login_city');
            }

            if (! Schema::hasColumn('users', 'last_login_country')) {
                $table->string('last_login_country')->nullable()->after('last_login_region');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                $columns = collect([
                    'current_route',
                    'current_url',
                    'login_at',
                    'city',
                    'region',
                    'country',
                ])->filter(fn ($column) => Schema::hasColumn('sessions', $column))->all();

                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $columns = collect([
                'last_login_ip',
                'last_login_city',
                'last_login_region',
                'last_login_country',
            ])->filter(fn ($column) => Schema::hasColumn('users', $column))->all();

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
