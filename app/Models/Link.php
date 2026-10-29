<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Link extends Model
{
    use HasFactory, SoftDeletes;

    /* ======================================================
       Mass Assignment
    ====================================================== */
    protected $fillable = [
        'user_id',
        'name',
        'url',
        'logo',
        'qr_code',
        'scan_token',
        'expires_at',
        'status',
    ];

    /* ======================================================
       Attribute Casting
    ====================================================== */
    protected $casts = [
        'expires_at' => 'date:Y-m-d',
    ];

    /* ======================================================
       Auto-Generate Secure Scan Token
    ====================================================== */
    protected static function booted(): void
    {
        static::creating(function (Link $link) {
            if (empty($link->scan_token)) {
                $link->scan_token = (string) Str::uuid();
            }
        });
    }

    /* ======================================================
       Relationships
    ====================================================== */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(LinkScan::class);
    }

    /* ======================================================
       Expiry Helpers
    ====================================================== */

    /** Check if QR is expired */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at
            ? Carbon::now()->gt($this->expires_at)
            : false;
    }

    /** Human-readable expiry status */
    public function getExpiryStatusAttribute(): string
    {
        if (! $this->expires_at) {
            return 'No Expiry';
        }

        return $this->is_expired ? 'Expired' : 'Active';
    }

    /* ======================================================
       Query Scopes
    ====================================================== */

    /** Only active QRs */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Only expired QRs */
    public function scopeExpired($query)
    {
        return $query->whereDate('expires_at', '<', now());
    }
}
