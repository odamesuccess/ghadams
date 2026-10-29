<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsSpecial = ! Schema::hasColumn('roles', 'is_special');
        $needsLanding = ! Schema::hasColumn('roles', 'landing_route');

        if (! $needsSpecial && ! $needsLanding) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) use ($needsSpecial, $needsLanding): void {
            if ($needsSpecial) {
                $table
                    ->boolean('is_special')
                    ->default(false)
                    ->after('guard_name');
            }

            if ($needsLanding) {
                $table
                    ->string('landing_route')
                    ->nullable()
                    ->after('is_special');
            }
        });
    }

    public function down(): void
    {
        $columns = [];

        if (Schema::hasColumn('roles', 'landing_route')) {
            $columns[] = 'landing_route';
        }

        if (Schema::hasColumn('roles', 'is_special')) {
            $columns[] = 'is_special';
        }

        if ($columns !== []) {
            Schema::table('roles', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};