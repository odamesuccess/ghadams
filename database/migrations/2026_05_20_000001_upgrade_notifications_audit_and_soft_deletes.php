<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables intentionally excluded because soft-deleting them can break Laravel's internals.
     */
    private array $excludedTables = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'migrations',
        'personal_access_tokens',
    ];

    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('audit_logs', 'properties')) {
                    $table->json('properties')->nullable()->after('new_values');
                }

                if (! Schema::hasColumn('audit_logs', 'ip_country')) {
                    $table->string('ip_country')->nullable()->index()->after('ip_address');
                }

                if (! Schema::hasColumn('audit_logs', 'ip_city')) {
                    $table->string('ip_city')->nullable()->index()->after('ip_country');
                }
            });
        }

        foreach ($this->applicationTables() as $tableName) {
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
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (Schema::hasColumn('audit_logs', 'ip_city')) {
                    $table->dropColumn('ip_city');
                }

                if (Schema::hasColumn('audit_logs', 'ip_country')) {
                    $table->dropColumn('ip_country');
                }

                if (Schema::hasColumn('audit_logs', 'properties')) {
                    $table->dropColumn('properties');
                }
            });
        }
    }

    private function applicationTables(): array
    {
        $database = DB::getDatabaseName();

        // SQLite (test suite) has no SHOW TABLES; MySQL keeps the original query.
        $rows = DB::getDriverName() === 'sqlite'
            ? array_map(fn ($name) => (object) ['Tables_in_'.$database => $name], Schema::getTableListing(schemaQualified: false))
            : DB::select('SHOW TABLES');

        $tables = collect($rows)
            ->map(function ($row) use ($database) {
                $property = 'Tables_in_'.$database;
                return $row->{$property} ?? array_values((array) $row)[0] ?? null;
            })
            ->filter()
            ->reject(fn ($table) => in_array($table, $this->excludedTables, true))
            ->values()
            ->all();

        return $tables;
    }
};
