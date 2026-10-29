<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetSource extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Relationships
     * One asset source can fund many assets
     */
    public function assets()
    {
        return $this->hasMany(Asset::class, 'asset_source_id');
    }
}
