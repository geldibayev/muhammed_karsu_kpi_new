<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentForm extends Model
{
    public const PRIMARY_WORKPLACE_ID = 11;

    public const EXTERNAL_PART_TIME_ID = 13;

    protected $fillable = [
        'id',
        'name',
    ];

    public $incrementing = false;
}
