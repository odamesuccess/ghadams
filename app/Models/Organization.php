<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organizations_name',
        'organizations_email',
        'organizations_phone',
        'organizations_address',
        'organization_abbreviation',
    ];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }
}
