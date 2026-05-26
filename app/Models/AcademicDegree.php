<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDegree extends Model
{
    //
    protected $fillable = [
        'id',
        'name',
    ];
    public $incrementing = false;
}
