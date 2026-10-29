<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('roles', 'is_special')) {
            Schema::table('roles', fn (Blueprint $table) => $table->boolean('is_special')->default(false));
        }
        if (!Schema::hasColumn('roles', 'landing_route')) {
            Schema::table('roles', fn (Blueprint $table) => $table->string('landing_route')->nullable());
        }
        DB::table('roles')->where('name', 'Special Role')->update(['is_special' => true]);
    }
    public function down(): void
    {
        // Existing role metadata and landing settings are retained.
    }
};
