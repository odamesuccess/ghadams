<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_suggestions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            // full | price_only
            $table->string('mode', 20)->default('full');

            // model, manufacturer, specifications, price (null when research failed)
            $table->json('payload')->nullable();

            // [{url, note}]
            $table->json('sources')->nullable();

            $table->enum('confidence', ['low', 'medium', 'high'])->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('retrieved_at')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['asset_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_suggestions');
    }
};
