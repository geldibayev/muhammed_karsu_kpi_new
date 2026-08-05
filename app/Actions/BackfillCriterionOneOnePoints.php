<?php

namespace App\Actions;

use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\Report;
use App\Support\EducationalContentCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BackfillCriterionOneOnePoints
{
    /** @return array{total: int, changed: int, unchanged: int, unresolved_ids: array<int, int>} */
    public function preview(Report $report): array
    {
        $data = $this->acceptedDataQuery($report)->get();
        $result = $this->emptyResult();
        [$targets, $unresolvedIds] = $this->targetsFor($data);
        $result['unresolved_ids'] = $unresolvedIds;

        foreach ($data as $datum) {
            $result['total']++;
            $target = $targets[$datum->getKey()] ?? null;

            if ($target === null) {
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
            [$resolvedTargets, $unresolvedIds] = $this->targetsFor($data);
            $result['unresolved_ids'] = $unresolvedIds;

            foreach ($data as $datum) {
                $result['total']++;
                $target = $resolvedTargets[$datum->getKey()] ?? null;

                if ($target === null) {
                    continue;
                }

                if ($this->needsUpdate($datum, $target)) {
                    $result['changed']++;
                } else {
                    $result['unchanged']++;
                }
            }

            if ($result['unresolved_ids'] !== []) {
                return $result;
            }

            foreach ($data as $datum) {
                if (isset($resolvedTargets[$datum->getKey()])
                    && $this->needsUpdate($datum, $resolvedTargets[$datum->getKey()])) {
                    $this->applyTarget($datum, $resolvedTargets[$datum->getKey()]);
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
    private function targetForResourceType(Datum $datum, ?string $resourceType): ?array
    {
        if ($datum->criterion === null || $datum->user === null) {
            return null;
        }

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

    /**
     * @param  Collection<int, Datum>  $data
     * @return array{array<int, array{option: CriterionManualScoreOption, point: float, percentage: int}>, array<int, int>}
     */
    private function targetsFor(Collection $data): array
    {
        $targets = [];
        $unresolvedIds = [];

        foreach ($data->groupBy(fn (Datum $datum): string => $datum->user_id.':'.$datum->criterion_id) as $group) {
            $usedResourceTypes = [];
            $duplicates = [];

            foreach ($group as $datum) {
                $resourceType = $this->resourceTypeFor($datum);

                if ($resourceType === null) {
                    $unresolvedIds[] = $datum->getKey();
                } elseif (in_array($resourceType, $usedResourceTypes, true)) {
                    $duplicates[] = $datum;
                } else {
                    $usedResourceTypes[] = $resourceType;
                    $target = $this->targetForResourceType($datum, $resourceType);

                    if ($target === null) {
                        $unresolvedIds[] = $datum->getKey();
                    } else {
                        $targets[$datum->getKey()] = $target;
                    }
                }
            }

            $availableResourceTypes = array_values(array_diff(
                array_keys(EducationalContentCriterionRule::PERCENTAGES),
                $usedResourceTypes,
            ));

            foreach ($duplicates as $datum) {
                $resourceType = array_shift($availableResourceTypes);
                $target = $this->targetForResourceType($datum, $resourceType);

                if ($target === null) {
                    $unresolvedIds[] = $datum->getKey();
                } else {
                    $targets[$datum->getKey()] = $target;
                }
            }
        }

        return [$targets, array_values(array_unique($unresolvedIds))];
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
