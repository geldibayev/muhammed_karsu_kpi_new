<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Criterion extends Model
{
    public const H_INDEX_CODE = '3/23';

    protected $fillable = [
        'id', 'name', 'desc', 'parent_id', 'template',
        'upload', 'file_limit', 'observation', 'report_id', 'res_type',
        'formula_id', 'integrate', 'checking', 'ai_prompt', 'ai_model', 'status',
        'ai_submission_max_point', 'divide_ai_point_by_authors',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Criterion::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Criterion::class, 'parent_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
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
        return $this->hasMany(Datum::class, 'criterion_id');
    }

    public function reviewerAssignment(): HasOne
    {
        return $this->hasOne(CriterionReviewerAssignment::class);
    }

    public function years(): BelongsToMany
    {
        return $this->belongsToMany(Year::class, 'criterion_years');
    }

    public function isHIndexCriterion(): bool
    {
        if ($this->relationLoaded('reviewerAssignment')) {
            return $this->reviewerAssignment?->criterion_code === self::H_INDEX_CODE;
        }

        return $this->reviewerAssignment()
            ->where('criterion_code', self::H_INDEX_CODE)
            ->exists();
    }

    public function manualScoreOptions(): HasMany
    {
        return $this->hasMany(CriterionManualScoreOption::class)
            ->where('active', true)
            ->orderBy('sort_order');
    }

    public function aiSubmissionMaximum(float $evaluationMaximum = 0): float
    {
        if ($this->ai_submission_max_point !== null) {
            return max(0, (float) $this->ai_submission_max_point);
        }

        if ((int) $this->formula_id === 3) {
            return max(0, (float) config('kpi.ai_unlimited_submission_max_point', 1));
        }

        return max(0, $evaluationMaximum);
    }

    protected function casts(): array
    {
        return [
            'name' => 'json',
            'desc' => 'json',
            'ai_submission_max_point' => 'float',
            'divide_ai_point_by_authors' => 'boolean',
        ];
    }
}
