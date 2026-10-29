<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable as C;
return new class extends Migration {
 public function up(): void {
  $now=C::now('Africa/Accra');
  $next=$now->startOfYear()->addYears($now->month>=7?1:0)->addMonths(6);
  DB::table('asset_report_schedules')->where('frequency','half_year')->update(['next_run_at'=>$next]);
 }
 public function down(): void { /* Preserve settings; do not enable additional deliveries. */ }
};
