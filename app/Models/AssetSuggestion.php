<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AssetSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const MODE_FULL = 'full';

    public const MODE_PRICE_ONLY = 'price_only';

    protected $fillable = [
        'asset_id',
        'mode',
        'payload',
        'sources',
        'confidence',
        'status',
        'error_message',
        'retrieved_at',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sources' => 'array',
        'retrieved_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ================= SCOPES / HELPERS ================= */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function hasFailed(): bool
    {
        return filled($this->error_message);
    }
}
