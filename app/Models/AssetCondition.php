<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCondition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conditionname',

    ];
}
