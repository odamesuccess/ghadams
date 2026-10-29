<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetLifecycleHistory extends Model
{
    use SoftDeletes;

    protected $fillable = ['asset_id','changed_by','old_status','new_status','reason','metadata','changed_at'];

    protected $casts = [
        'metadata' => 'array',
        'changed_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
