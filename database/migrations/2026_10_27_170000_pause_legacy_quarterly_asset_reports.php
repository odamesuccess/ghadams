<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void { DB::table('asset_report_schedules')->where('frequency','quarterly')->update(['enabled'=>false,'next_run_at'=>null]); }
 public function down(): void { /* Do not reactivate email schedules during rollback. */ }
};
