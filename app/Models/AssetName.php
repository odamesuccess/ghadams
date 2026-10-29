<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetName extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'assetname',
        'brand',
        'usefullife',
        'assetname_abbreviation',
    ];
}
