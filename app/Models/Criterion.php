<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Criterion extends Model
{
    protected $fillable = [
        'id', 'name', 'desc', 'parent_id', 'template',
        'upload', 'file_limit', 'observation', 'report_id', 'res_type',
        'formula_id', 'integrate', 'checking', 'ai_prompt', 'ai_model', 'status',
    ];

    protected $casts = [
        'name' => 'json',
        'desc' => 'json',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Criterion::class, 'parent_id');
    }

    public function criterionEvaluation($criterion_id, $evaluation)
    {
        return CriterionEvaluation::where('criterion_id', $criterion_id)->where('evaluation', $evaluation)->first();
    }

    public function criterionEvaluations(): HasMany
    {
        return $this->hasMany(CriterionEvaluation::class, 'criterion_id');
    }

    public function criterionPoints(): HasMany
    {
        return $this->hasMany(CriterionPoint::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(Datum::class, 'criterion_id')
            ->where('user_id', auth()->id())->orderBy('created_at', 'desc');
    }
}
