<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemNotification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'title',
        'message',
        'notifiable_record_type',
        'notifiable_record_id',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function notifiableRecord()
    {
        return $this->morphTo(__FUNCTION__, 'notifiable_record_type', 'notifiable_record_id');
    }
}
