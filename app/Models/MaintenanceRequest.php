<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_id',
        'requested_by',
        'approved_by',
        'completed_by',
        'maintenance_provider_id',
        'status',
        'priority',
        'fault_description',
        'estimated_cost',
        'actual_cost',
        'scheduled_date',
        'expected_completion_date',
        'actual_completion_date',
        'approval_comment',
        'completion_note',
        'before_photo_path',
        'after_photo_path',
        'receipt_path',
        'payment_status',
        'payment_date',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'scheduled_date' => 'date:Y-m-d',
        'expected_completion_date' => 'date:Y-m-d',
        'actual_completion_date' => 'date:Y-m-d',
        'payment_date' => 'datetime',
    ];

    public function asset(){ return $this->belongsTo(Asset::class); }
    public function requester(){ return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(){ return $this->belongsTo(User::class, 'approved_by'); }
    public function completer(){ return $this->belongsTo(User::class, 'completed_by'); }
    public function maintenanceProvider(){ return $this->belongsTo(MaintenanceProvider::class, 'maintenance_provider_id'); }
}
