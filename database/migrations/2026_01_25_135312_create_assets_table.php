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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // =========================
            // Public Identifiers
            // =========================
            $table->uuid('uuid')->unique();
            $table->uuid('scan_token')->unique();

            // =========================
            // Core Identity
            // =========================
            $table->string('asset_code')->unique();

            $table->foreignId('assetname_id')
                ->constrained('asset_names')
                ->restrictOnDelete();

            $table->string('brand')->nullable();

            $table->foreignId('source_id')
                ->constrained('sources')
                ->restrictOnDelete();

            // =========================
            // Financials
            // =========================
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->unsignedInteger('warranty_duration')->nullable();
            $table->date('warranty_end_date')->nullable();

            // =========================
            // Description & Media
            // =========================
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('asset_image')->nullable();
            $table->string('qr_code')->nullable();

            // =========================
            // Relationships
            // =========================
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_id')
                ->constrained('maintenance_providers')
                ->restrictOnDelete();
            $table->foreignId('asset_condition_id')
                ->constrained('asset_conditions')
                ->restrictOnDelete();
            $table->foreignId('asset_location_id')
                ->constrained('asset_locations')
                ->restrictOnDelete();
            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            // =========================
            // Lifecycle Status
            // =========================
            $table->enum('status', [
                'active',
                'under_maintenance',
                'obsolete',
                'disposed',
            ])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
