<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QrReprintHistory extends Model
{
    use SoftDeletes;

    protected $fillable = ['asset_id','printed_by','reason','ip_address','user_agent'];
    public function asset(){ return $this->belongsTo(Asset::class); }
    public function printedBy(){ return $this->belongsTo(User::class, 'printed_by'); }
}
