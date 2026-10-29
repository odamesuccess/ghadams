<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_expiry_alert_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')
                ->constrained('assets')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('recipient_email');
            $table->string('alert_type', 32);
            $table->date('expiry_date');
            $table->timestamp('sent_at');

            $table->timestamps();

            $table->unique(
                [
                    'asset_id',
                    'user_id',
                    'alert_type',
                    'expiry_date',
                ],
                'asset_expiry_alert_unique'
            );

            $table->index(
                ['alert_type', 'expiry_date'],
                'asset_expiry_alert_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_expiry_alert_logs');
    }
};
