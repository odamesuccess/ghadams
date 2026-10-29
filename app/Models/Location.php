<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    // 👇 THIS is the key line
    protected $table = 'asset_locations';

    protected $fillable = [
        'locationname',
        'officenumber',
    ];

    /* ================= RELATIONSHIP ================= */

    public function assets()
    {
        return $this->hasMany(Asset::class, 'asset_location_id');
    }
}
