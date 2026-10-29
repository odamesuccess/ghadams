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
        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code')->unique();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('asset_id')
                ->nullable()
                ->constrained('assets')
                ->nullOnDelete();

            $table->string('asset_name');
            $table->string('asset_category')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('preferred_specification')->nullable();
            $table->text('justification')->nullable();

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('needed_date')->nullable();

            $table->enum('status', ['pending', 'reviewed', 'approved', 'rejected', 'supplied', 'cancelled'])
                ->default('pending');

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('supplied_at')->nullable();
            $table->text('review_comment')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['unit_id', 'status']);
            $table->index(['requested_by', 'status']);
            $table->index(['priority', 'status']);
            $table->index(['needed_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requests');
    }
};
