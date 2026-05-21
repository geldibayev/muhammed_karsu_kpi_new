<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Datum extends Model
{
    protected $table = 'data';

    protected $fillable = [
        'id', 'name', 'material', 'criterion_id', 'status', 'year_id', 'language_id'
    ];

    protected $casts = [
        'material' => 'json',
    ];
}
