<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ownership registry for synthetic demonstration data.
 *
 * Every row created by the synthetic demo dataset generator is listed here, so
 * the generator can replace or remove exactly its own rows and nothing else.
 * It also holds procurement batch references and invoice/payment references
 * that the core tables have no column for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_dataset_records', function (Blueprint $table) {
            $table->id();
            $table->string('dataset', 64);
            $table->string('record_type', 64);
            $table->unsignedBigInteger('record_id');
            $table->string('batch_ref', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['record_type', 'record_id']);
            $table->index(['dataset', 'record_type']);
            $table->index('batch_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_dataset_records');
    }
};
