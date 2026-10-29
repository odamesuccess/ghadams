<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceProvider extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'providername',
        'contactperson',
        'phonenumber',
        'email',
    ];
}
