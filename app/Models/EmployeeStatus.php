<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeStatus extends Model
{
    //
    public $incrementing = false;
    protected $fillable = [
        'id',
        'name',
    ];
}
