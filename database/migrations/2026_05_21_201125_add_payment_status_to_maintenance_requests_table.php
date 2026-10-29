<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('maintenance_requests', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->after('actual_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('maintenance_requests', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });
    }
};
