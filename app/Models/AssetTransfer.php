<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AssetTransfer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transfer_code',
        'asset_id',
        'from_unit_id',
        'to_unit_id',
        'from_user_id',
        'to_user_id',
        'requested_by',
        'approved_by',
        'completed_by',
        'reason',
        'remarks',
        'status',
        'requested_at',
        'approved_at',
        'completed_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AssetTransfer $assetTransfer): void {
            if (empty($assetTransfer->transfer_code)) {
                $assetTransfer->transfer_code = self::generateTransferCode();
            }

            if (empty($assetTransfer->requested_at)) {
                $assetTransfer->requested_at = now();
            }
        });
    }

    public static function generateTransferCode(): string
    {
        do {
            $code = 'AT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (self::where('transfer_code', $code)->exists());

        return $code;
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromUnit()
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
