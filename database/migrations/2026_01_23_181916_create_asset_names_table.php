<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_names', function (Blueprint $table) {
            $table->id();
            $table->string('assetname')->unique();
            $table->string('assetname_abbreviation')->unique();
            $table->string('brand')->unique();
            $table->integer('usefullife');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_names');
    }
};
