<?php
namespace App\Services;
use App\Models\{Asset,MaintenanceRequest,User,AssetReportSchedule};
use Carbon\CarbonImmutable as C;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\{DB,Mail,Cache};
use RuntimeException;
class AssetPeriodReports {
 public const TYPES=['maintenance'=>'Maintenance Cost','quantity'=>'Asset Quantity','warranty'=>'Warranty Expiry'];
 public const FREQUENCIES=['monthly'=>'Monthly','half_year'=>'Half-year','annual'=>'Annual'];
 public function bounds(string $frequency, ?C $at=null): array {
  $at=($at??C::now('Africa/Accra'))->setTimezone('Africa/Accra');
  if($frequency==='half_year'){
   $start=$at->startOfYear()->subYears($at->month<7?1:0);
   return [$start,$start->addMonths(6)->subDay()->endOfDay()];
  }
  $boundary=match($frequency){'monthly'=>$at->startOfMonth(),'half_year'=>$at->startOfYear()->addMonths($at->month>6?6:0),'annual'=>$at->startOfYear(),default=>throw new RuntimeException('Invalid frequency')};
  $end=$boundary->subDay()->endOfDay();
  $start=match($frequency){'monthly'=>$end->startOfMonth(),'half_year'=>$end->startOfYear()->addMonths($end->month>6?6:0),'annual'=>$end->startOfYear()};
  return [$start,$end];
 }
 public function next(string $frequency): C {
  $now=C::now('Africa/Accra');
  return match($frequency){'monthly'=>$now->startOfMonth()->addMonth(),'half_year'=>$now->startOfYear()->addYears($now->month>=7?1:0)->addMonths(6),'annual'=>$now->startOfYear()->addYear()};
 }
 public function recipients(AssetReportSchedule $s) {
  if (!$s->role_ids) return collect();
  return User::query()->whereIn('user_status',['Active','IsSystem'])
   ->where(fn($q)=>$q->where('user_status','IsSystem')->orWhereNull('account_expires_at')->orWhere('account_expires_at','>',now()))
   ->whereHas('roles',fn($q)=>$q->whereIn('roles.id',$s->role_ids)->whereNull('roles.deleted_at'))
   ->orderBy('id')->get(['id','name','email'])->filter(fn($u)=>filter_var($u->email,FILTER_VALIDATE_EMAIL))
   ->unique(fn($u)=>strtolower(trim($u->email)))->values();
 }
 public function data(AssetReportSchedule $s, ?C $reference=null, ?C $cutoff=null): array {
  [$start,$end]=$this->bounds($s->frequency,$reference);
  if($cutoff && $cutoff->lt($end)) $end=$cutoff->endOfDay();
  $today=C::now('Africa/Accra')->startOfDay();
  $d=['title'=>self::FREQUENCIES[$s->frequency].' '.self::TYPES[$s->report_type], 'period'=>$start->format('d M Y').' to '.$end->format('d M Y'), 'generated'=>$today->toDateString(),'headers'=>[],'rows'=>[],'summary'=>[],'note'=>''];
  if($s->report_type==='maintenance') {
   $jobs=MaintenanceRequest::with(['asset.assetName','asset.unit'])->where('status','completed')->whereBetween('actual_completion_date',[$start->toDateString(),$end->toDateString()])->orderBy('actual_completion_date')->get();
   $d['headers']=['Asset','Unit','Completed','Actual cost (GHS)'];
   $d['rows']=$jobs->map(fn($j)=>[$j->asset?->asset_code??'Unavailable',$j->asset?->unit?->name??'Unassigned',optional($j->actual_completion_date)->format('Y-m-d'),$j->actual_cost===null?'Not recorded':number_format((float)$j->actual_cost,2)])->all();
   $d['summary']=['Completed jobs'=>$jobs->count(),'Recorded actual cost (GHS)'=>number_format((float)$jobs->sum('actual_cost'),2),'Jobs missing actual cost'=>$jobs->whereNull('actual_cost')->count()];
   $d['note']='Completed maintenance only, grouped by actual completion date. Missing actual costs are disclosed and estimates are not treated as expenditure. This is not a cash-payment statement. Deleted jobs are excluded.';
  } elseif($s->report_type==='quantity') {
   $base=Asset::with(['assetName','unit']);
   $purchases=(clone $base)->whereBetween('purchase_date',[$start->toDateString(),$end->toDateString()])->get();
   $d['headers']=['Asset type','Unit','Purchased quantity'];
   $d['rows']=$purchases->groupBy(fn($a)=>($a->assetName?->assetname??'Unspecified').'|||'.($a->unit?->name??'Unassigned'))->map(function($g,$key){return [...explode('|||',$key),$g->count()];})->values()->all();
   $d['summary']=['Purchased in reporting period'=>$purchases->count(),'Current registered assets'=>Asset::count()];
   $d['note']='Period quantity uses purchase date, not record creation date. The register count is current at generation, includes all statuses and excludes soft-deleted records. Historical opening/closing balances and disposal totals are not inferred. Current unit assignments are shown.';
  } else {
   $until=$today->addDays($s->warning_days);
   $assets=Asset::with(['assetName','unit'])->whereNotIn('status',['disposed','obsolete'])->whereBetween('warranty_end_date',[$today->toDateString(),$until->toDateString()])->orderBy('warranty_end_date')->get();
   $d['headers']=['Asset','Asset type','Unit','Warranty ends','Days remaining'];
   $d['rows']=$assets->map(fn($a)=>[$a->asset_code,$a->assetName?->assetname??'Unspecified',$a->unit?->name??'Unassigned',$a->warranty_end_date->format('Y-m-d'),(int)$today->diffInDays(C::parse($a->warranty_end_date)->startOfDay())])->all();
   $d['summary']=['Expiring within '.$s->warning_days.' days'=>$assets->count()];
   $d['note']='Forward-looking warranty snapshot from '.$today->toDateString().' through '.$until->toDateString().', inclusive. Excludes expired, obsolete, disposed and soft-deleted assets. Warranty expiry does not mean the asset itself is unusable.';
  }
  return $d;
 }
 public function assertMailTransport(): void {
  if(!in_array(config('mail.default'),['smtp','sendmail','ses','mailgun','postmark','resend','failover','roundrobin'],true)) throw new RuntimeException('Configure a delivery mail transport; log/array mailers do not deliver reports.');
 }
 public function pdf(array $data): string { return Pdf::loadView('reports.period-pdf',$data)->setPaper('a4','landscape')->output(); }
 public function send(AssetReportSchedule $s): array {
  return Cache::lock('asset-period-report-'.$s->id,3600)->block(2,function()use($s){
   $recipients=$this->recipients($s); if($recipients->isEmpty()) throw new RuntimeException('No eligible users in the selected roles.');
   [$start,$end]=$this->bounds($s->frequency); $period=$start->toDateString().'_'.$end->toDateString();
   $data=$this->data($s); $pdf=$this->pdf($data); $sent=0; $failed=0; $skipped=0;
   $this->assertMailTransport();
   foreach($recipients as $u){
    $id=DB::table('asset_report_deliveries')->insertOrIgnore(['schedule_id'=>$s->id,'period'=>$period,'user_id'=>$u->id,'email'=>strtolower(trim($u->email)),'status'=>'sending','created_at'=>now(),'updated_at'=>now()]);
    if(!$id){$skipped++;continue;}
    try {
     Mail::send('reports.period-email',['report'=>$data],function($m)use($u,$pdf,$data,$s,$period){$m->to($u->email)->subject('GhanaDAMS: '.$data['title'].' | '.$data['period'])->attachData($pdf,$s->report_type.'-'.$period.'.pdf',['mime'=>'application/pdf']);});
     DB::table('asset_report_deliveries')->where(['schedule_id'=>$s->id,'period'=>$period,'user_id'=>$u->id])->update(['status'=>'sent','sent_at'=>now(),'updated_at'=>now()]);$sent++;
    }catch(\Throwable $e){
     report($e); DB::table('asset_report_deliveries')->where(['schedule_id'=>$s->id,'period'=>$period,'user_id'=>$u->id])->update(['status'=>'uncertain','error'=>'Delivery was not confirmed. Check the server mail log before resending.','updated_at'=>now()]);$failed++;
    }
   }
   return compact('sent','failed','skipped');
  });
 }
}
