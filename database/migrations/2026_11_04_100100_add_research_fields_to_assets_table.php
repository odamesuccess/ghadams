<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds nullable columns used by AI asset research. Existing columns are not changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('model_number')->nullable()->after('brand');
            $table->string('part_number')->nullable()->after('serial_number');
            $table->json('specifications')->nullable()->after('description');

            // Researched market price. Separate from purchase_cost (what was actually paid).
            $table->decimal('market_price_amount', 12, 2)->nullable()->after('purchase_cost');
            $table->decimal('market_price_low', 12, 2)->nullable()->after('market_price_amount');
            $table->decimal('market_price_high', 12, 2)->nullable()->after('market_price_low');
            $table->char('market_price_currency', 3)->nullable()->after('market_price_high');
            $table->string('market_price_type', 20)->nullable()->after('market_price_currency');
            $table->timestamp('market_price_retrieved_at')->nullable()->after('market_price_type');

            $table->index(['brand', 'model_number']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['brand', 'model_number']);
            $table->dropColumn([
                'model_number',
                'part_number',
                'specifications',
                'market_price_amount',
                'market_price_low',
                'market_price_high',
                'market_price_currency',
                'market_price_type',
                'market_price_retrieved_at',
            ]);
        });
    }
};
