<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_scans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('link_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            /* ===== Analytics fields ===== */
            $table->string('device')->nullable();
            $table->string('browser')->nullable();

            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();

            $table->timestamps();

            /* ===== Performance indexes ===== */
            $table->index('country');
            $table->index('region');
            $table->index('city');
            $table->index('created_at');

            // Critical composite index for analytics queries
            $table->index(['created_at', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_scans');
    }
};
