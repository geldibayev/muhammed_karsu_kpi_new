<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeStatus extends Model
{
    public const WORKING_ID = 11;

    //
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
    ];
}
