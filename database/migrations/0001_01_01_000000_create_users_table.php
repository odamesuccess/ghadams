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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            // Status
            $table->enum('user_status', ['Active', 'Inactive', 'IsSystem'])
                ->default('Active');

            // Basic info
            $table->string('name');
            $table->string('sex', 10)->nullable();
            $table->string('email')->unique();
            $table->string('contact_number', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('profile_image')->nullable();

            // Security
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->boolean('force_password_change')->default(false);
            $table->timestamp('password_changed_time')->nullable();

            // Two-factor auth
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
