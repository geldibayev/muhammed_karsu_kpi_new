<?php

namespace App\Actions;

use App\Enums\DatumStatus;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Formula;
use App\Models\Report;
use App\Models\User;
use App\Support\TeachingQualityScoreSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyTeachingQualityScoreSnapshot
{
    public const CRITERION_CODE = '1.5';

    public const SYSTEM_KEY = 'teaching-quality-survey';

    public function __construct(private RecalculateReportPoints $recalculateReportPoints) {}

    /**
     * @return array{
     *     rows: int,
     *     matched_users: int,
     *     missing_users: int,
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     removed: int,
     *     conflicts: int,
     *     missing_hemis_ids: list<string>
     * }
     */
    public function handle(Report $report, bool $apply): array
    {
        $scores = $this->validatedScores();
        $criterion = $this->criterion($report);

        if (! $apply) {
            return $this->process($criterion, $scores, false);
        }

        return DB::transaction(function () use ($report, $criterion, $scores): array {
            Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $lockedCriterion = Criterion::query()->lockForUpdate()->findOrFail($criterion->getKey());
            $result = $this->process($lockedCriterion, $scores, true);

            if ($result['created'] + $result['updated'] + $result['removed'] > 0) {
                $this->recalculateReportPoints->handle($report);
            }

            return $result;
        }, attempts: 3);
    }

    /**
     * @param  array<string, string>  $scores
     * @return array{
     *     rows: int,
     *     matched_users: int,
     *     missing_users: int,
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     removed: int,
     *     conflicts: int,
     *     missing_hemis_ids: list<string>
     * }
     */
    private function process(Criterion $criterion, array $scores, bool $apply): array
    {
        $users = User::query()
            ->select(['id', 'hemis_id'])
            ->whereIn('hemis_id', array_keys($scores))
            ->orderBy('id')
            ->when($apply, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get();
        $usersByHemisId = $users->keyBy(fn (User $user): string => (string) $user->hemis_id);
        $missingHemisIds = array_values(array_diff(array_keys($scores), $usersByHemisId->keys()->all()));
        $conflicts = Datum::query()
            ->whereBelongsTo($criterion)
            ->whereIn('user_id', $users->modelKeys())
            ->where('status', '!=', DatumStatus::Deleted->value)
            ->where(function (Builder $query): void {
                $query->whereNull('system_key')
                    ->orWhere('system_key', '!=', self::SYSTEM_KEY);
            })
            ->when($apply, fn (Builder $query): Builder => $query->lockForUpdate())
            ->count();

        if ($apply && $conflicts > 0) {
            throw new RuntimeException(
                "1.5 mezonida {$conflicts} ta boshqa resurs bor. Ikki marta ball bermaslik uchun import to‘xtatildi.",
            );
        }

        $existingData = Datum::query()
            ->whereBelongsTo($criterion)
            ->where('system_key', self::SYSTEM_KEY)
            ->with('user:id,hemis_id')
            ->when($apply, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get();
        $existingByUserId = $existingData->keyBy('user_id');
        $result = [
            'rows' => count($scores),
            'matched_users' => $users->count(),
            'missing_users' => count($missingHemisIds),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'removed' => 0,
            'conflicts' => $conflicts,
            'missing_hemis_ids' => $missingHemisIds,
        ];

        foreach ($usersByHemisId as $hemisId => $user) {
            $point = $scores[$hemisId];
            $attributes = $this->datumAttributes($hemisId, $point);
            $datum = $existingByUserId->get($user->getKey()) ?? new Datum([
                'user_id' => $user->getKey(),
                'criterion_id' => $criterion->getKey(),
                'system_key' => self::SYSTEM_KEY,
            ]);

            if (! $datum->exists) {
                $result['created']++;
            } elseif ($this->matches($datum, $attributes)) {
                $result['unchanged']++;

                continue;
            } else {
                $result['updated']++;
            }

            if (! $apply) {
                continue;
            }

            $oldPoint = $datum->exists ? (float) $datum->point : null;
            $oldStatus = $datum->exists ? $datum->status : null;
            $datum->fill($attributes);
            $datum->save();
            $datum->histories()->create([
                'user_id' => $user->getKey(),
                'type' => 'success',
                'message' => $this->historyMessage($point, $oldPoint, $oldStatus),
                'message_type' => 'teaching_quality_score_assigned',
            ]);
        }

        $staleData = $existingData->filter(function (Datum $datum) use ($scores): bool {
            $hemisId = (string) $datum->user?->hemis_id;

            return $hemisId === '' || ! array_key_exists($hemisId, $scores);
        });

        foreach ($staleData as $datum) {
            if ($datum->status === DatumStatus::Deleted->value && $datum->point === 0.0) {
                continue;
            }

            $result['removed']++;

            if (! $apply) {
                continue;
            }

            $oldPoint = $datum->point;
            $datum->update([
                'status' => DatumStatus::Deleted->value,
                'point' => 0,
                'reason' => 'HEMIS ID yangi o‘qitish sifati snapshotida yo‘qligi sababli tizim balli hisobdan chiqarildi.',
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'warning',
                'message' => 'O‘qitish sifati snapshotidagi tizim balli hisobdan chiqarildi. Oldingi ball: '
                    .number_format($oldPoint, 2, '.', '').'.',
                'message_type' => 'teaching_quality_score_removed',
            ]);
        }

        return $result;
    }

    private function criterion(Report $report): Criterion
    {
        if ($report->code !== TeachingQualityScoreSnapshot::REPORT_CODE) {
            throw new RuntimeException(
                'Snapshot faqat '.TeachingQualityScoreSnapshot::REPORT_CODE.' hisobotiga tegishli.',
            );
        }

        $criterion = Criterion::query()
            ->whereBelongsTo($report)
            ->where('code', self::CRITERION_CODE)
            ->whereNotNull('parent_id')
            ->with('formula:id,code')
            ->first();

        if ($criterion === null
            || $criterion->status !== '1'
            || $criterion->checking !== 'hemis:vote'
            || $criterion->upload !== '0'
            || ! $criterion->usesFormula(Formula::Maximum)) {
            throw new RuntimeException(
                'Faol 1.5 “O‘qitish sifati darajasi” mezoni topilmadi yoki uning konfiguratsiyasi noto‘g‘ri. Renumber migratsiyasini tekshiring.',
            );
        }

        return $criterion;
    }

    /** @return array<string, string> */
    private function validatedScores(): array
    {
        $scores = [];
        $normalizedRows = [];

        foreach (TeachingQualityScoreSnapshot::rows() as $index => $row) {
            $hemisId = $row['hemis_id'];
            $point = $row['point'];
            $sourceRow = $index + 2;

            if (preg_match('/^\d{10}$/', $hemisId) !== 1) {
                throw new RuntimeException("Snapshotning {$sourceRow}-qatoridagi HEMIS ID yaroqsiz.");
            }

            if (array_key_exists($hemisId, $scores)) {
                throw new RuntimeException("Snapshotda HEMIS ID {$hemisId} takrorlangan.");
            }

            if (! is_numeric($point) || ! is_finite((float) $point) || (float) $point < 0 || (float) $point > 10) {
                throw new RuntimeException("Snapshotning {$sourceRow}-qatoridagi ball yaroqsiz.");
            }

            $scores[$hemisId] = number_format((float) $point, 2, '.', '');
            $normalizedRows[] = $hemisId.'|'.$scores[$hemisId];
        }

        if ($scores === []) {
            throw new RuntimeException('O‘qitish sifati ballari snapshoti bo‘sh.');
        }

        if (hash('sha256', implode("\n", $normalizedRows)) !== TeachingQualityScoreSnapshot::DATA_SHA256) {
            throw new RuntimeException('O‘qitish sifati ballari snapshotining nazorat summasi mos emas.');
        }

        return $scores;
    }

    /** @return array<string, array<string, string>|float|null|string> */
    private function datumAttributes(string $hemisId, string $point): array
    {
        return [
            'name' => '1.5 — o‘qitish sifati bo‘yicha anketa bahosi',
            'material' => [
                'type' => 'system',
                'source' => TeachingQualityScoreSnapshot::SOURCE,
                'source_sha256' => TeachingQualityScoreSnapshot::SOURCE_SHA256,
                'data_sha256' => TeachingQualityScoreSnapshot::DATA_SHA256,
                'hemis_id' => $hemisId,
                'survey_point' => $point,
            ],
            'status' => DatumStatus::Accepted->value,
            'point' => (float) $point,
            'reason' => "Alohida o‘tkazilgan anketa bo‘yicha o‘rtacha ball: {$point}.",
            'reviewer_hemis_id' => null,
        ];
    }

    /** @param  array<string, array<string, string>|float|null|string>  $attributes */
    private function matches(Datum $datum, array $attributes): bool
    {
        return $datum->name === $attributes['name']
            && $datum->material === $attributes['material']
            && $datum->status === $attributes['status']
            && abs($datum->point - (float) $attributes['point']) < 0.00005
            && $datum->reason === $attributes['reason']
            && $datum->reviewer_hemis_id === null;
    }

    private function historyMessage(string $point, ?float $oldPoint, ?string $oldStatus): string
    {
        if ($oldPoint === null) {
            return "O‘qitish sifati anketasi bo‘yicha {$point} ball berildi."
                .' Manba SHA-256: '.TeachingQualityScoreSnapshot::SOURCE_SHA256.'.';
        }

        return 'O‘qitish sifati anketasi balli yangilandi. Oldingi holat/ball: '
            .($oldStatus ?? 'yo‘q').'/'.number_format($oldPoint, 2, '.', '')
            .". Yangi holat/ball: accepted/{$point}."
            .' Manba SHA-256: '.TeachingQualityScoreSnapshot::SOURCE_SHA256.'.';
    }
}
