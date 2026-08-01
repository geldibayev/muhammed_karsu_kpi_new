<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'code', 'name', 'desc', 'starts_on', 'ends_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'json',
            'desc' => 'json',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
