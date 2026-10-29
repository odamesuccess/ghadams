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
        Schema::create('asset_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();        // IGF, GOV, DONOR, NGO
            $table->text('description')->nullable(); // Purpose
            $table->timestamps();
        });

    }
};
