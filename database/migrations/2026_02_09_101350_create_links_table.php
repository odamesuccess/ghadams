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
        Schema::create('links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('url')->unique();
            $table->string('logo')->nullable();
            $table->string('qr_code')->nullable();

            // secure public token for QR redirect
            $table->uuid('scan_token')->unique()->nullable();

            $table->unsignedBigInteger('scan_count')->default(0);
            $table->date('expires_at')->nullable();

            $table->enum('status', ['active', 'expired', 'disabled'])
                ->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
