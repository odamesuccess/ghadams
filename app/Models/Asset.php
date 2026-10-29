<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Asset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'scan_token',
        'asset_code',
        'assetname_id',
        'brand',
        'source_id',
        'supplier_id',
        'maintenance_id',
        'purchase_cost',
        'purchase_date',
        'warranty_duration',
        'warranty_end_date',
        'description',
        'serial_number',
        'asset_image',
        'qr_code',
        'status',
        'user_id',
        'unit_id',
        'organization_id',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'warranty_end_date' => 'date:Y-m-d',
        'purchase_cost' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($asset) {
            $asset->uuid ??= (string) Str::uuid();
            $asset->scan_token ??= (string) Str::uuid();
        });
    }

    /* ================= RELATIONSHIPS ================= */

    public function assetName()
    {
        return $this->belongsTo(AssetName::class, 'assetname_id');
    }

    public function source()
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function maintenanceProvider()
    {
        return $this->belongsTo(MaintenanceProvider::class, 'maintenance_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lifecycleHistories()
    {
        return $this->hasMany(AssetLifecycleHistory::class);
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function approvalRequests()
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function assetRequests()
    {
        return $this->hasMany(AssetRequest::class);
    }

    public function assetTransfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }


    public function scanLogs()
    {
        return $this->hasMany(AssetScanLog::class);
    }
}

