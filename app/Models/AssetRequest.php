<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AssetRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_code',
        'unit_id',
        'requested_by',
        'reviewed_by',
        'asset_id',
        'asset_name',
        'asset_category',
        'quantity',
        'preferred_specification',
        'justification',
        'priority',
        'needed_date',
        'status',
        'reviewed_at',
        'supplied_at',
        'review_comment',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'needed_date' => 'date:Y-m-d',
        'reviewed_at' => 'datetime',
        'supplied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AssetRequest $assetRequest): void {
            if (empty($assetRequest->request_code)) {
                $assetRequest->request_code = self::generateRequestCode();
            }
        });
    }

    public static function generateRequestCode(): string
    {
        do {
            $code = 'AR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (self::where('request_code', $code)->exists());

        return $code;
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isSupplied(): bool
    {
        return $this->status === 'supplied';
    }
}
