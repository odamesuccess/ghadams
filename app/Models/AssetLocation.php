<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'locationname',
        'officenumber',
    ];

    public function assets()
    {
        return $this->hasMany(Asset::class, 'asset_location_id');
    }
}
