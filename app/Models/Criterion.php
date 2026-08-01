<?php

namespace App\Models;

use App\Support\OakArticleCriterionRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Criterion extends Model
{
    public const H_INDEX_CODE = '3.1.4';

    protected $fillable = [
        'id', 'code', 'name', 'desc', 'parent_id', 'sort_order', 'template',
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

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
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
        if (filled($this->code)) {
            return $this->code === self::H_INDEX_CODE;
        }

        return $this->reviewerAssignment()
            ->whereIn('criterion_code', [self::H_INDEX_CODE, '3/23'])
            ->exists();
    }

    public function isOakArticleCriterion(): bool
    {
        return $this->code === OakArticleCriterionRule::CODE;
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

        if ($this->usesFormula(Formula::Unlimited)) {
            return max(0, (float) config('kpi.ai_unlimited_submission_max_point', 1));
        }

        return max(0, $evaluationMaximum);
    }

    public function usesFormula(string $code): bool
    {
        $formula = $this->relationLoaded('formula')
            ? $this->formula
            : $this->formula()->first(['id', 'code']);

        if (filled($formula?->code)) {
            return $formula->code === $code;
        }

        $legacyCode = match ((int) $this->formula_id) {
            1 => Formula::Competition,
            2 => Formula::Maximum,
            3 => Formula::Unlimited,
            default => null,
        };

        return $legacyCode === $code;
    }

    protected function casts(): array
    {
        return [
            'name' => 'json',
            'desc' => 'json',
            'ai_submission_max_point' => 'float',
            'divide_ai_point_by_authors' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
