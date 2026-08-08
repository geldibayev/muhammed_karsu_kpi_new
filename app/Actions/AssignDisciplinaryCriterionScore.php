<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\DisciplinarySanction;
use App\Models\DisciplinarySanctionImport;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AssignDisciplinaryCriterionScore
{
    public const CRITERION_CODE = '4.1.6';

    public const SYSTEM_KEY = 'disciplinary-status';

    public function __construct(private RecalculateReportPoints $recalculateReportPoints) {}

    /** @return array{ready: bool, sanctioned: bool, changed: int, report_ids: array<int, int>} */
    public function handle(User $user, bool $recalculate = true): array
    {
        if (! DisciplinarySanctionImport::query()->exists()) {
            return [
                'ready' => false,
                'sanctioned' => false,
                'changed' => 0,
                'report_ids' => [],
            ];
        }

        $sanctioned = DisciplinarySanction::query()
            ->where('hemis_id', (string) $user->hemis_id)
            ->exists();
        $criteria = Criterion::query()
            ->where('code', self::CRITERION_CODE)
            ->where('status', '1')
            ->whereHas('report', fn (Builder $query): Builder => $query->where('status', '1'))
            ->with('report')
            ->get();
        $changed = 0;
        $reportIds = [];

        foreach ($criteria as $criterion) {
            $wasChanged = DB::transaction(function () use ($user, $criterion, $sanctioned): bool {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $lockedCriterion = Criterion::query()->lockForUpdate()->findOrFail($criterion->getKey());
                $point = $sanctioned ? 0.0 : 2.0;
                $reason = $sanctioned
                    ? 'Intizomiy jazo ro‘yxatida mavjudligi sababli 4.1.6 mezoni uchun 0 ball berildi.'
                    : 'Intizomiy jazo ro‘yxatida mavjud emasligi sababli 4.1.6 mezoni uchun 2 ball berildi.';
                $datum = Datum::query()->firstOrNew([
                    'user_id' => $user->getKey(),
                    'criterion_id' => $lockedCriterion->getKey(),
                    'system_key' => self::SYSTEM_KEY,
                ]);
                $isNew = ! $datum->exists;
                $oldPoint = $isNew ? null : (float) $datum->point;
                $oldStatus = $isNew ? null : $datum->status;
                $hasChanged = $isNew
                    || $oldPoint !== $point
                    || $oldStatus !== 'accepted'
                    || $datum->reason !== $reason;

                if (! $hasChanged) {
                    return false;
                }

                $datum->fill([
                    'name' => '4.1.6 — intizomiy holat bo‘yicha avtomatik baholash',
                    'material' => [
                        'type' => 'system',
                        'source' => 'disciplinary_sanctions',
                    ],
                    'status' => 'accepted',
                    'point' => $point,
                    'reason' => $reason,
                    'reviewer_hemis_id' => null,
                ]);
                $datum->save();

                $change = $isNew
                    ? "Yangi avtomatik ball: {$point}."
                    : 'Oldingi holat/ball: '.($oldStatus ?? 'yo‘q').'/'.number_format((float) $oldPoint, 2, '.', '')
                        .". Yangi holat/ball: accepted/{$point}.";
                $datum->histories()->create([
                    'user_id' => $user->getKey(),
                    'type' => $sanctioned ? 'warning' : 'success',
                    'message' => $reason.' '.$change,
                    'message_type' => 'disciplinary_score_assigned',
                ]);

                return true;
            }, attempts: 3);

            $changed += (int) $wasChanged;
            $reportIds[] = (int) $criterion->report_id;
        }

        $reportIds = array_values(array_unique($reportIds));
        if ($recalculate && $changed > 0) {
            Report::query()
                ->whereKey($reportIds)
                ->each(fn (Report $report): mixed => $this->recalculateReportPoints->handle($report));
        }

        return [
            'ready' => true,
            'sanctioned' => $sanctioned,
            'changed' => $changed,
            'report_ids' => $reportIds,
        ];
    }
}
