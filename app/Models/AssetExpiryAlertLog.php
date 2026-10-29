<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetExpiryAlertLog extends Model
{
    protected $fillable = [
        'asset_id',
        'user_id',
        'recipient_email',
        'alert_type',
        'expiry_date',
        'sent_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
