<?php

namespace App\Actions;

use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\Report;
use App\Support\EducationalContentCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BackfillCriterionOneOnePoints
{
    /** @return array{total: int, changed: int, unchanged: int, unresolved_ids: array<int, int>} */
    public function preview(Report $report): array
    {
        $result = $this->emptyResult();

        foreach ($this->acceptedDataQuery($report)->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            $result['total']++;
            $target = $this->targetFor($datum);

            if ($target === null) {
                $result['unresolved_ids'][] = $datum->getKey();

                continue;
            }

            $result[$this->needsUpdate($datum, $target) ? 'changed' : 'unchanged']++;
        }

        return $result;
    }

    /** @return array{total: int, changed: int, unchanged: int, unresolved_ids: array<int, int>} */
    public function handle(Report $report): array
    {
        return DB::transaction(function () use ($report): array {
            Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $data = $this->acceptedDataQuery($report)->lockForUpdate()->get();
            $result = $this->emptyResult();
            $targets = [];

            foreach ($data as $datum) {
                $result['total']++;
                $target = $this->targetFor($datum);

                if ($target === null) {
                    $result['unresolved_ids'][] = $datum->getKey();

                    continue;
                }

                if ($this->needsUpdate($datum, $target)) {
                    $result['changed']++;
                    $targets[$datum->getKey()] = $target;
                } else {
                    $result['unchanged']++;
                }
            }

            if ($result['unresolved_ids'] !== []) {
                return $result;
            }

            foreach ($data as $datum) {
                if (isset($targets[$datum->getKey()])) {
                    $this->applyTarget($datum, $targets[$datum->getKey()]);
                }
            }

            return $result;
        }, 3);
    }

    private function acceptedDataQuery(Report $report): Builder
    {
        return Datum::query()
            ->select([
                'data.id',
                'data.user_id',
                'data.criterion_id',
                'data.manual_score_option_id',
                'data.status',
                'data.point',
            ])
            ->where('data.status', 'accepted')
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->whereBelongsTo($report)
                ->where('code', EducationalContentCriterionRule::CODE))
            ->with($this->relations())
            ->orderBy('data.id');
    }

    /** @return array<int|string, mixed> */
    private function relations(): array
    {
        return [
            'user:id,degree',
            'manualScoreOption:id,criterion_id,code',
            'criterion:id,code,report_id',
            'criterion.manualScoreOptions:id,criterion_id,code,label,point,active,sort_order',
            'criterion.criterionEvaluations:id,criterion_id,evaluation,has,score',
            'histories' => fn ($query) => $query
                ->select(['id', 'datum_id', 'message', 'message_type', 'created_at'])
                ->where('message_type', 'manual_review_approved')
                ->latest('id'),
        ];
    }

    /** @param array{option: CriterionManualScoreOption, point: float, percentage: int} $target */
    private function applyTarget(Datum $datum, array $target): void
    {
        $oldPoint = (float) $datum->point;
        $datum->update([
            'point' => $target['point'],
            'manual_score_option_id' => $target['option']->getKey(),
        ]);
        $datum->histories()->create([
            'user_id' => $datum->user_id,
            'type' => 'info',
            'message' => '1.1 mezoni balli yangi foizli qoidaga muvofiq qayta hisoblandi. '
                .'Resurs turi: '.EducationalContentCriterionRule::labelFor($target['option']->code).'. '
                .'Ulush: '.$target['percentage'].'%. '
                .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                .'Yangi ball: '.number_format($target['point'], 4, '.', '').'.',
            'message_type' => 'criterion_1_1_point_recalculated',
        ]);
    }

    /** @param array{option: CriterionManualScoreOption, point: float, percentage: int} $target */
    private function needsUpdate(Datum $datum, array $target): bool
    {
        return $datum->manual_score_option_id !== $target['option']->getKey()
            || abs((float) $datum->point - $target['point']) >= 0.00005;
    }

    /** @return array{option: CriterionManualScoreOption, point: float, percentage: int}|null */
    private function targetFor(Datum $datum): ?array
    {
        if ($datum->criterion === null || $datum->user === null) {
            return null;
        }

        $resourceType = $this->resourceTypeFor($datum);
        $option = $resourceType === null
            ? null
            : $datum->criterion->manualScoreOptions->firstWhere('code', $resourceType);
        $evaluation = $datum->criterion->criterionEvaluations
            ->firstWhere('evaluation', $datum->user->degree);

        if (! $option instanceof CriterionManualScoreOption || $evaluation?->has !== '1') {
            return null;
        }

        $percentage = EducationalContentCriterionRule::percentageFor($option->code);
        $point = EducationalContentCriterionRule::pointFor((float) $evaluation->score, $option->code);

        if ($percentage === null || $point === null) {
            return null;
        }

        return compact('option', 'point', 'percentage');
    }

    private function resourceTypeFor(Datum $datum): ?string
    {
        if ($datum->manualScoreOption !== null
            && $datum->manualScoreOption->criterion_id === $datum->criterion_id
            && EducationalContentCriterionRule::percentageFor($datum->manualScoreOption->code) !== null) {
            return $datum->manualScoreOption->code;
        }

        foreach ($datum->histories as $history) {
            $resourceType = EducationalContentCriterionRule::resourceTypeFromHistory((string) $history->message);

            if ($resourceType !== null) {
                return $resourceType;
            }
        }

        return EducationalContentCriterionRule::resourceTypeFromLegacyPoint((float) $datum->point);
    }

    /** @return array{total: int, changed: int, unchanged: int, unresolved_ids: array<int, int>} */
    private function emptyResult(): array
    {
        return [
            'total' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'unresolved_ids' => [],
        ];
    }
}
