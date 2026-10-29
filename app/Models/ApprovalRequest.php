<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_id','requested_by','reviewed_by','type','status','title','reason','review_comment','payload','reviewed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function asset(){ return $this->belongsTo(Asset::class); }
    public function requester(){ return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(){ return $this->belongsTo(User::class, 'reviewed_by'); }
}
