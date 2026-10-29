<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable, SoftDeletes;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'name',
        'sex',
        'unit_id',
        'user_status',
        'email',
        'contact_number',
        'date_of_birth',
        'profile_image',
        'password',
        'force_password_change',
        'password_changed_time',
        'account_expires_at',
        'deactivation_reason',
        'deactivated_at',
        'deactivated_by',
        'last_login_at',
        'last_logout_at',
        'last_seen_at',
        'last_login_country',
        'last_login_region',
        'last_login_city',
        'last_login_ip',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'force_password_change' => 'boolean',
        'password_changed_time' => 'datetime',
        'account_expires_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_logout_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /* =========================
       RELATIONSHIPS
    ========================= */

    /**
     * A user belongs to one unit
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }


    /**
     * Admin who deactivated this account.
     */
    public function deactivatedBy()
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->user_status === 'IsSystem') {
                $user->account_expires_at = null;
            } elseif ($user->date_of_birth !== null) {
                $user->account_expires_at = $user->sixtiethBirthday();
            }
        });
    }

    public function sixtiethBirthday(): ?\Carbon\CarbonInterface
    {
        return $this->date_of_birth?->copy()->addYearsNoOverflow(60)->startOfDay();
    }

    public function isActive(): bool
    {
        return in_array($this->user_status, ['Active', 'IsSystem'], true);
    }

    public function isExpired(): bool
    {
        if ($this->user_status === 'IsSystem') {
            return false;
        }

        $expiry = $this->sixtiethBirthday() ?? $this->account_expires_at;
        return $expiry !== null && now()->greaterThanOrEqualTo($expiry);
    }

    public function canLogin(): bool
    {
        return $this->isActive() && ! $this->isExpired();
    }

    public function accountStatusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        return $this->user_status ?? 'Active';
    }

    /**
     * Password history
     */
    public function passwordHistories()
    {
        return $this->hasMany(PasswordHistory::class);
    }

    /**
     * User assets (if applicable)
     */
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }


    public function assetRequests()
    {
        return $this->hasMany(AssetRequest::class, 'requested_by');
    }

    public function reviewedAssetRequests()
    {
        return $this->hasMany(AssetRequest::class, 'reviewed_by');
    }

    public function assetTransfersRequested()
    {
        return $this->hasMany(AssetTransfer::class, 'requested_by');
    }

    public function assetTransfersApproved()
    {
        return $this->hasMany(AssetTransfer::class, 'approved_by');
    }

    public function assetTransfersCompleted()
    {
        return $this->hasMany(AssetTransfer::class, 'completed_by');
    }

    public function incomingAssetTransfers()
    {
        return $this->hasMany(AssetTransfer::class, 'to_user_id');
    }

    public function outgoingAssetTransfers()
    {
        return $this->hasMany(AssetTransfer::class, 'from_user_id');
    }
}
