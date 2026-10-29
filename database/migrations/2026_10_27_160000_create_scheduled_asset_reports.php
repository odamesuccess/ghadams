<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('asset_report_schedules', function(Blueprint $t){
   $t->id(); $t->string('report_type'); $t->string('frequency'); $t->json('role_ids');
   $t->unsignedInteger('warning_days')->default(90); $t->boolean('enabled')->default(false);
   $t->timestamp('next_run_at')->nullable(); $t->timestamps(); $t->unique(['report_type','frequency']);
  });
  Schema::create('asset_report_deliveries', function(Blueprint $t){
   $t->id(); $t->foreignId('schedule_id')->constrained('asset_report_schedules');
   $t->string('period'); $t->unsignedBigInteger('user_id'); $t->string('email');
   $t->string('status'); $t->text('error')->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamps();
   $t->unique(['schedule_id','period','user_id']); $t->unique(['schedule_id','period','email']);
  });
 }
 public function down(): void { Schema::dropIfExists('asset_report_deliveries'); Schema::dropIfExists('asset_report_schedules'); }
};
