<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetScanLog extends Model
{
    use SoftDeletes;

    protected $casts = [
        'is_unknown_location' => 'boolean',
        'is_tamper_suspected' => 'boolean',
    ];

    protected $fillable = [
        'asset_id',
        'scanned_by',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device',
        'scan_location',
        'is_unknown_location',
        'is_tamper_suspected',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}

