<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'landing_route')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table
                    ->string('landing_route')
                    ->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'landing_route')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropColumn('landing_route');
            });
        }
    }
};