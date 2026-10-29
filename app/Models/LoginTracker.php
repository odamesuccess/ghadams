<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoginTracker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device',
        'login_at',
        'logout_at',
        'session_duration_seconds',
        'description',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
