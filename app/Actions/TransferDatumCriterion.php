<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransferDatumCriterion
{
    /**
     * @return Collection<int, Criterion>
     */
    public function destinations(Datum $datum): Collection
    {
        $datum->loadMissing('criterion:id,report_id');
        $resourceType = data_get($datum->material, 'type');

        return Criterion::query()
            ->select(['id', 'name', 'parent_id', 'file_limit'])
            ->with('parent:id,name')
            ->withCount([
                'files as user_submission_count' => fn (Builder $query): Builder => $query
                    ->where('user_id', $datum->user_id)
                    ->countsTowardsUploadLimit(),
            ])
            ->where('report_id', $datum->criterion->report_id)
            ->whereKeyNot($datum->criterion_id)
            ->where('status', '1')
            ->where('upload', '1')
            ->whereNotNull('parent_id')
            ->where(function (Builder $query) use ($resourceType): void {
                $query->where('res_type', 'all');

                if (in_array($resourceType, ['file', 'url'], true)) {
                    $query->orWhere('res_type', $resourceType);
                }
            })
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Criterion $criterion): bool => $criterion->file_limit === 0
                || $criterion->user_submission_count < $criterion->file_limit)
            ->values();
    }

    public function handle(User $reviewer, Datum $datum, int $criterionId): Datum
    {
        return DB::transaction(function () use ($reviewer, $datum, $criterionId): Datum {
            $lockedDatum = Datum::query()
                ->with('criterion:id,name,report_id')
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('transferCriterion', $lockedDatum);

            $resourceType = data_get($lockedDatum->material, 'type');
            $targetCriterion = Criterion::query()
                ->whereKey($criterionId)
                ->where('report_id', $lockedDatum->criterion->report_id)
                ->where('status', '1')
                ->where('upload', '1')
                ->whereNotNull('parent_id')
                ->where(function (Builder $query) use ($resourceType): void {
                    $query->where('res_type', 'all');

                    if (in_array($resourceType, ['file', 'url'], true)) {
                        $query->orWhere('res_type', $resourceType);
                    }
                })
                ->lockForUpdate()
                ->first();

            if ($targetCriterion === null || $targetCriterion->is($lockedDatum->criterion)) {
                throw ValidationException::withMessages([
                    'criterion_id' => 'Tanlangan kriteriyaga ushbu resursni o‘tkazib bo‘lmaydi.',
                ]);
            }

            $this->ensureUploadLimitIsAvailable($lockedDatum, $targetCriterion);

            $oldCriterion = $lockedDatum->criterion;
            $message = sprintf(
                'Resurs “%s” (#%d) kriteriyasidan “%s” (#%d) kriteriyasiga o‘tkazildi.',
                data_get($oldCriterion->name, 'uz', 'Nomsiz kriteriya'),
                $oldCriterion->getKey(),
                data_get($targetCriterion->name, 'uz', 'Nomsiz kriteriya'),
                $targetCriterion->getKey(),
            );

            $lockedDatum->update([
                'criterion_id' => $targetCriterion->getKey(),
                'status' => 'checking',
                'point' => 0,
                'author_count' => null,
                'page_count' => null,
                'impact_factor' => null,
                'publication_tier' => null,
                'university_tier' => null,
                'received_amount' => null,
                'reviewer_hemis_id' => null,
                'reason' => 'Kriteriya o‘zgartirildi. Inson tekshiruvi kutilmoqda.',
            ]);
            $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'info',
                'message' => $message,
                'message_type' => 'criterion_transferred',
            ]);

            return $lockedDatum->refresh();
        }, 3);
    }

    private function ensureUploadLimitIsAvailable(Datum $datum, Criterion $criterion): void
    {
        if ($criterion->file_limit === 0) {
            return;
        }

        $submissionCount = Datum::query()
            ->where('user_id', $datum->user_id)
            ->where('criterion_id', $criterion->getKey())
            ->countsTowardsUploadLimit()
            ->count();

        if ($submissionCount >= $criterion->file_limit) {
            throw ValidationException::withMessages([
                'criterion_id' => 'Tanlangan kriteriyada foydalanuvchi uchun yuklash chegarasi to‘lgan.',
            ]);
        }
    }
}
