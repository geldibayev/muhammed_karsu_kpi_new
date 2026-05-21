<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Criterion extends Model
{
    protected $fillable = [
        'id', 'name', 'desc', 'parent_id', 'template',
        'upload', 'file_limit', 'observation', 'report_id',
        'formula_id', 'integrate', 'checking', 'ai_prompt', 'status',
    ];

    protected $casts = [
        'name' => 'json',
        'desc' => 'json',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Criterion::class, 'parent_id');
    }

    public function criterionEvaluation(): HasOne
    {
        return $this->hasOne(CriterionEvaluation::class, 'criterion_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(Datum::class, 'criterion_id')->where('user_id', auth()->id());
    }
}
