<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Route,Schedule};
use App\Http\Controllers\AssetPeriodReportController as Controller;
class AssetReportsServiceProvider extends ServiceProvider {
 public function boot(): void {
  if(!$this->app->routesAreCached()) Route::middleware(['web','auth','force.password.change','account.active','permission:scheduled-reports.manage'])->group(function(){
   Route::get('/scheduled-reports',[Controller::class,'index'])->name('scheduled-reports.index');
   Route::get('/scheduled-reports/preview',[Controller::class,'samplePdf'])->name('scheduled-reports.preview');
   Route::get('/scheduled-reports/recipients',[Controller::class,'recipients'])->name('scheduled-reports.options');
   Route::post('/scheduled-reports/test',[Controller::class,'test'])->middleware('throttle:3,1')->name('scheduled-reports.test');
   Route::post('/scheduled-reports',[Controller::class,'save'])->name('scheduled-reports.save');
   Route::get('/scheduled-reports/{schedule}/pdf',[Controller::class,'preview'])->name('scheduled-reports.pdf');
   Route::post('/scheduled-reports/{schedule}/send',[Controller::class,'send'])->name('scheduled-reports.send');
  });
  $this->app->booted(fn()=>Schedule::command('reports:deliver-scheduled')->dailyAt('07:15')->timezone('Africa/Accra')->withoutOverlapping(120));
 }
}
