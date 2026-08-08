<?php

namespace App\Models;

use App\Support\FixedPerResourceHumanReviewCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiHumanReviewAssignment extends Model
{
    protected $fillable = [
        'hemis_id',
        'active_slot',
        'assigned_at',
        'ended_at',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active_slot', 1);
    }

    public static function activeHemisId(): ?int
    {
        $hemisId = static::query()->active()->value('hemis_id');

        return is_numeric($hemisId) ? (int) $hemisId : null;
    }

    public static function reviewerHemisIdFor(Criterion $criterion, bool $sharedLock = false): ?int
    {
        $criterionReviewers = config('kpi.ai_human_review_criterion_reviewers', []);
        $criterionReviewerHemisId = is_array($criterionReviewers)
            ? ($criterionReviewers[$criterion->code] ?? null)
            : null;

        if (is_numeric($criterionReviewerHemisId) && (int) $criterionReviewerHemisId > 0) {
            return (int) $criterionReviewerHemisId;
        }

        if ($criterion->code === FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE) {
            $existingReviewerQuery = $criterion->reviewerAssignment();

            if ($sharedLock) {
                $existingReviewerQuery->sharedLock();
            }

            $existingReviewerHemisId = $existingReviewerQuery->value('hemis_id');

            if (is_numeric($existingReviewerHemisId) && (int) $existingReviewerHemisId > 0) {
                return (int) $existingReviewerHemisId;
            }
        }

        $query = static::query()->active();

        if ($sharedLock) {
            $query->sharedLock();
        }

        $hemisId = $query->value('hemis_id');

        return is_numeric($hemisId) ? (int) $hemisId : null;
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hemis_id', 'hemis_id');
    }

    protected function casts(): array
    {
        return [
            'hemis_id' => 'integer',
            'active_slot' => 'integer',
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
