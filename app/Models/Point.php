<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Point extends Model
{
    //
    protected $fillable = [
        'user_id',
        'criterion_id',
        'point',
        'report_id',
    ];
}
