<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type')->default('system')->index();
            $table->string('title');
            $table->text('message')->nullable();

            // Short custom index name to avoid MySQL 64-character index limit
            $table->nullableMorphs('notifiable_record', 'sys_notif_record_idx');

            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'sys_notif_user_read_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
