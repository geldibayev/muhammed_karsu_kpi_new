<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentForm extends Model
{
    public const PRIMARY_WORKPLACE_ID = 11;

    protected $fillable = [
        'id',
        'name',
    ];

    public $incrementing = false;
}
