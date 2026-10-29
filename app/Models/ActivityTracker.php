<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityTracker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'trackable_type',
        'trackable_id',
        'title',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trackable()
    {
        return $this->morphTo();
    }
}
