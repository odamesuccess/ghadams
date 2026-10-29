<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssetReportSchedule extends Model {
 protected $guarded = ['id'];
 protected $casts = ['role_ids'=>'array','enabled'=>'boolean','next_run_at'=>'immutable_datetime'];
}
