<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CriterionPoint extends Model
{
    //
    protected $fillable = [
        'user_id',
        'criterion_id',
        'report_id',
        'point',
        'files',
    ];

    public function criterion(): HasOne
    {
        return $this->hasOne(Criterion::class, 'id', 'criterion_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
