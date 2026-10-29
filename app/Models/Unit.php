<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'unit_abbreviation',
        'email',
        'phonenumber',
        'organization_id',
        'locationname',
        'location_abbreviation',
        'officenumber',
    ];

    /* ============================
       RELATIONSHIPS
    ============================ */

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function assetRequests()
    {
        return $this->hasMany(AssetRequest::class);
    }

    public function outgoingAssetTransfers()
    {
        return $this->hasMany(AssetTransfer::class, 'from_unit_id');
    }

    public function incomingAssetTransfers()
    {
        return $this->hasMany(AssetTransfer::class, 'to_unit_id');
    }


    /* ============================
       ACCESSORS (Optional UX Boost)
    ============================ */

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->unit_abbreviation})";
    }
}
