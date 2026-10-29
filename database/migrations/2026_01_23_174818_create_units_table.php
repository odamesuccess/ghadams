<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit_abbreviation');
            $table->string('email');
            $table->string('phonenumber');
            $table->string('locationname');
            $table->string('location_abbreviation');
            $table->string('officenumber');
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('units', 'organization_id')) {
            Schema::table('units', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }
    }
};
