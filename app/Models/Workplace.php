<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workplace extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'academic_degree_id',
        'academic_rank_id',
        'form_id',
        'staff_id',
        'staff_position_id',
        'status_id',
        'type_id',
    ];
}
