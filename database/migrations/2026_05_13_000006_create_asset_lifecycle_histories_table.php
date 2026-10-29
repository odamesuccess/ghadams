<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_lifecycle_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status')->index();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
            $table->index(['asset_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_lifecycle_histories');
    }
};
