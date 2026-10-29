<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\AssetReportSchedule;
use App\Services\AssetPeriodReports;
class DeliverScheduledAssetReports extends Command {
 protected $signature='reports:deliver-scheduled {--dry-run : List due schedules without sending}';
 protected $description='Deliver due monthly, half-year and annual reports to selected roles';
 public function handle(AssetPeriodReports $service): int {
  $failed=0;
  foreach(AssetReportSchedule::whereIn('frequency',array_keys(AssetPeriodReports::FREQUENCIES))->where('enabled',true)->where('next_run_at','<=',now())->get() as $s){
   $this->line($s->report_type.' / '.$s->frequency.' / recipients: '.$service->recipients($s)->count());
   if($this->option('dry-run'))continue;
   try{$r=$service->send($s);$this->line(json_encode($r));$s->update(['next_run_at'=>$service->next($s->frequency)]);if($r['failed'])$failed++;}
   catch(\Throwable $e){report($e);$this->error('Delivery failed. Check application log.');$failed++;}
  }
  return $failed?self::FAILURE:self::SUCCESS;
 }
}
