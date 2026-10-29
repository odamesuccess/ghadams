<?php
namespace App\Http\Controllers;
use App\Models\{AssetReportSchedule,Role};
use App\Services\AssetPeriodReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class AssetPeriodReportController extends Controller {
 public function index(AssetPeriodReports $service){
  return Inertia::render('Reports/Scheduled',[
   'schedules'=>AssetReportSchedule::whereIn('frequency',array_keys(AssetPeriodReports::FREQUENCIES))->orderBy('id')->get()->map(fn($s)=>array_merge($s->toArray(),['recipients'=>$service->recipients($s)->map->only(['id','name','email'])->values()])),
   'roles'=>Role::where('guard_name','web')->orderBy('name')->get(['id','name']),
   'types'=>AssetPeriodReports::TYPES,'frequencies'=>AssetPeriodReports::FREQUENCIES,
   'deliveries'=>DB::table('asset_report_deliveries')->orderByDesc('id')->limit(100)->get(),
  ]);
 }


 private function sample(Request $request,AssetPeriodReports $service): array {
  $v=$request->validate(['report_type'=>['required',Rule::in(array_keys(AssetPeriodReports::TYPES))],'frequency'=>['required',Rule::in(array_keys(AssetPeriodReports::FREQUENCIES))],'warning_days'=>['required','integer',Rule::in([30,60,90])]]);
  $s=new AssetReportSchedule($v);
  $today=\Carbon\CarbonImmutable::now('Africa/Accra')->startOfDay();
  $reference=match($v['frequency']){'monthly'=>$today->startOfMonth()->addMonth(),'half_year'=>$today->startOfYear()->addMonths(6),'annual'=>$today->startOfYear()->addYear()};
  return $service->data($s,$reference,$today);
 }
 public function samplePdf(Request $request,AssetPeriodReports $service){
  $data=$this->sample($request,$service);
  return response($service->pdf($data),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="ghadams-selected-period.pdf"','Cache-Control'=>'private, no-store']);
 }

 private function selectedRecipients(Request $request,AssetPeriodReports $service) {
  $v=$request->validate(['role_ids'=>['required','array','min:1'],'role_ids.*'=>['integer',Rule::exists('roles','id')->whereNull('deleted_at')->where('guard_name','web')]]);
  return $service->recipients(new AssetReportSchedule(['role_ids'=>$v['role_ids']]));
 }
 public function recipients(Request $request,AssetPeriodReports $service){
  return response()->json(['recipients'=>$this->selectedRecipients($request,$service)->map->only(['id','name','email'])->values()])->header('Cache-Control','private, no-store');
 }

 public function test(Request $request,AssetPeriodReports $service){
  $recipients=$this->selectedRecipients($request,$service);
  if($recipients->isEmpty())return back()->withErrors(['role_ids'=>'No eligible users with valid email addresses belong to the selected roles.']);
  $request->validate(['warning_days'=>['required','integer',Rule::in([30,60,90])]]);
  $reports=[];$attachments=[];
  try {
   $service->assertMailTransport();
   foreach(AssetPeriodReports::FREQUENCIES as $frequency=>$label){
    foreach(AssetPeriodReports::TYPES as $type=>$title){
     $input=clone $request;$input->merge(['frequency'=>$frequency,'report_type'=>$type]);
     $data=$this->sample($input,$service);$data['title']='TEST — '.$data['title'];
     $data['note']='TEST REPORT. Scheduled delivery history is unchanged. '.$data['note'];
     $reports[]=$data;
     $attachments[]=['name'=>'TEST-'.$frequency.'-'.$type.'.pdf','bytes'=>$service->pdf($data)];
    }
   }
   $size=array_sum(array_map(fn($a)=>strlen($a['bytes']),$attachments));
   if($size>15*1024*1024)return back()->withErrors(['delivery'=>'The nine PDFs exceed the 15 MB attachment safeguard. No test emails were sent. Reduce report size or arrange a download-link delivery before retrying.']);
  }catch(\Throwable $e){report($e);return back()->withErrors(['delivery'=>'The complete test bundle could not be prepared. Check PDF and SMTP configuration in the server log. No partial bundle was sent.']);}
  $sent=0;$failed=0;
  foreach($recipients as $user){
   try {
    \Illuminate\Support\Facades\Mail::send('reports.period-test-bundle',['reports'=>$reports],function($m)use($user,$attachments){
     $m->to($user->email)->subject('[TEST] GhanaDAMS: All 9 Monthly, Half-year and Annual Reports');
     foreach($attachments as $attachment)$m->attachData($attachment['bytes'],$attachment['name'],['mime'=>'application/pdf']);
    });$sent++;
   }catch(\Throwable $e){report($e);$failed++;}
  }
  return back()->with('success',"All-nine-report test bundles: {$sent} accepted by mail transport; {$failed} unconfirmed. Each accepted email contains all 9 PDFs. Check inbox/spam and server mail logs. Scheduled delivery history was unchanged.");
 }
 public function save(Request $request,AssetPeriodReports $service){
  $v=$request->validate(['report_type'=>['required',Rule::in(array_keys(AssetPeriodReports::TYPES))],'frequency'=>['required',Rule::in(array_keys(AssetPeriodReports::FREQUENCIES))],'role_ids'=>['required','array','min:1'],'role_ids.*'=>['integer',Rule::exists('roles','id')->whereNull('deleted_at')->where('guard_name','web')],'warning_days'=>['required','integer',Rule::in([30,60,90])],'enabled'=>['required','boolean']]);
  $s=AssetReportSchedule::firstOrNew(['report_type'=>$v['report_type'],'frequency'=>$v['frequency']]);
  if(!$s->exists || (!$s->enabled && $v['enabled']))$s->next_run_at=$service->next($v['frequency']);
  $s->fill($v)->save();return back()->with('success','Report schedule saved.');
 }
 public function preview(AssetReportSchedule $schedule,AssetPeriodReports $service){
  abort_unless(isset(AssetPeriodReports::FREQUENCIES[$schedule->frequency]),422,'This legacy frequency is no longer supported. Create a half-year schedule.');
  return response($service->pdf($service->data($schedule)),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="ghadams-report.pdf"','Cache-Control'=>'private, no-store']);
 }
 public function send(AssetReportSchedule $schedule,AssetPeriodReports $service){
  abort_unless(isset(AssetPeriodReports::FREQUENCIES[$schedule->frequency]),422,'This legacy frequency is no longer supported. Create a half-year schedule.');
  try{$result=$service->send($schedule);return back()->with('success',"Sent: {$result['sent']}; unconfirmed: {$result['failed']}; already attempted: {$result['skipped']}.");}
  catch(\Throwable $e){report($e);return back()->withErrors(['delivery'=>'Report could not be sent. Check eligible recipients, mail configuration and server logs.']);}
 }
}
