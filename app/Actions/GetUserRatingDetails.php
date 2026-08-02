<?php

namespace App\Actions;

use App\Enums\DatumStatus;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class GetUserRatingDetails
{
    /**
     * @return array{
     *     report: Report|null,
     *     user: User,
     *     criterionSections: Collection<int, array{criterion: Criterion, number: int, rows: Collection<int, array{criterion: Criterion, code: string, state: string, point: float|null, pending_count: int, accepted_submissions: Collection<int, Datum>, cancelled_submissions: Collection<int, Datum>, evaluators: Collection<int, array{type: string, name: string}>}>}>,
     *     totalPoints: float
     * }
     */
    public function handle(User $user): array
    {
        $report = Report::query()
            ->where('status', '1')
            ->latest('id')
            ->first(['id', 'name']);

        $user->load([
            'ratingWorkplace.position',
            'ratingWorkplace.department.parent',
        ]);

        $points = $this->points($user, $report);
        $historiesByCriterion = $this->historiesByCriterion($user, $report, $points);
        $submissionsByCriterion = $this->submissionsByCriterion($user, $report);

        return [
            'report' => $report,
            'user' => $user,
            'criterionSections' => $this->criterionSections(
                $report,
                $points->keyBy('criterion_id'),
                $historiesByCriterion,
                $submissionsByCriterion,
                $user->degree ?? '',
            ),
            'totalPoints' => (float) $points->sum('point'),
        ];
    }

    /**
     * @param  Collection<int, Point>  $pointsByCriterion
     * @param  Collection<int, Collection<int, DatumHistory>>  $historiesByCriterion
     * @param  Collection<int, Collection<int, Datum>>  $submissionsByCriterion
     * @return Collection<int, array{criterion: Criterion, number: int, rows: Collection<int, array{criterion: Criterion, code: string, state: string, point: float|null, pending_count: int, accepted_submissions: Collection<int, Datum>, cancelled_submissions: Collection<int, Datum>, evaluators: Collection<int, array{type: string, name: string}>}>}>
     */
    private function criterionSections(
        ?Report $report,
        Collection $pointsByCriterion,
        Collection $historiesByCriterion,
        Collection $submissionsByCriterion,
        string $evaluationCategory,
    ): Collection {
        if ($report === null) {
            return collect();
        }

        return Criterion::query()
            ->select(['id', 'code', 'name', 'report_id', 'sort_order'])
            ->whereBelongsTo($report)
            ->whereNull('parent_id')
            ->where('status', '1')
            ->with([
                'children' => fn (HasMany $query): HasMany => $query
                    ->select(['id', 'code', 'name', 'parent_id', 'checking', 'formula_id', 'sort_order'])
                    ->where('status', '1')
                    ->with([
                        'formula:id,code,name',
                        'criterionEvaluations:id,criterion_id,evaluation,has,score',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (Criterion $parent, int $index) use (
                $pointsByCriterion,
                $historiesByCriterion,
                $submissionsByCriterion,
                $evaluationCategory,
            ): array {
                $sectionNumber = $index + 1;

                return [
                    'criterion' => $parent,
                    'number' => $sectionNumber,
                    'rows' => $parent->children->map(fn (Criterion $criterion): array => $this->criterionRow(
                        $criterion,
                        $sectionNumber,
                        $pointsByCriterion->get($criterion->getKey()),
                        $historiesByCriterion->get($criterion->getKey(), collect()),
                        $submissionsByCriterion->get($criterion->getKey(), collect()),
                        $evaluationCategory,
                    )),
                ];
            });
    }

    /**
     * @param  Collection<int, DatumHistory>  $histories
     * @param  Collection<int, Datum>  $submissions
     * @return array{criterion: Criterion, code: string, state: string, point: float|null, pending_count: int, accepted_submissions: Collection<int, Datum>, cancelled_submissions: Collection<int, Datum>, evaluators: Collection<int, array{type: string, name: string}>}
     */
    private function criterionRow(
        Criterion $criterion,
        int $sectionNumber,
        ?Point $point,
        Collection $histories,
        Collection $submissions,
        string $evaluationCategory,
    ): array {
        $pendingCount = $submissions
            ->whereIn('status', [DatumStatus::Received->value, DatumStatus::Checking->value])
            ->count();
        $acceptedSubmissions = $submissions
            ->where('status', DatumStatus::Accepted->value)
            ->values();
        $cancelledSubmissions = $submissions
            ->where('status', DatumStatus::Cancelled->value)
            ->values();
        $ratingMethod = $this->ratingMethod($criterion, $evaluationCategory);

        if ($point !== null) {
            $evaluators = $this->evaluators($criterion, $histories);

            if ($pendingCount > 0) {
                $evaluators->push(['type' => 'pending', 'name' => 'Baholash kutilmoqda']);
            }

            return $this->row(
                $criterion,
                $sectionNumber,
                'scored',
                $point->point,
                $pendingCount,
                $acceptedSubmissions,
                $cancelledSubmissions,
                $evaluators,
                $ratingMethod,
            );
        }

        if ($pendingCount > 0) {
            return $this->row(
                $criterion,
                $sectionNumber,
                'pending',
                null,
                $pendingCount,
                $acceptedSubmissions,
                $cancelledSubmissions,
                collect([['type' => 'pending', 'name' => 'Baholash kutilmoqda']]),
                $ratingMethod,
            );
        }

        if ($submissions->contains('status', DatumStatus::Accepted->value)) {
            return $this->row(
                $criterion,
                $sectionNumber,
                'accepted',
                null,
                0,
                $acceptedSubmissions,
                $cancelledSubmissions,
                collect([['type' => 'status', 'name' => 'Yakuniy ball hisoblanmoqda']]),
                $ratingMethod,
            );
        }

        if ($submissions->contains('status', DatumStatus::Cancelled->value)) {
            return $this->row(
                $criterion,
                $sectionNumber,
                'cancelled',
                null,
                0,
                $acceptedSubmissions,
                $cancelledSubmissions,
                collect([['type' => 'status', 'name' => 'Ma’lumot qaytarilgan']]),
                $ratingMethod,
            );
        }

        return $this->row(
            $criterion,
            $sectionNumber,
            'unuploaded',
            null,
            0,
            $acceptedSubmissions,
            $cancelledSubmissions,
            collect([['type' => 'unuploaded', 'name' => 'Ma’lumot yuklanmagan']]),
            $ratingMethod,
        );
    }

    /**
     * @param  Collection<int, Datum>  $acceptedSubmissions
     * @param  Collection<int, Datum>  $cancelledSubmissions
     * @param  Collection<int, array{type: string, name: string}>  $evaluators
     * @param  array{key: string, label: string, explanation: string, note: string, example: string, maximum: float|null}  $ratingMethod
     * @return array{criterion: Criterion, code: string, state: string, point: float|null, pending_count: int, accepted_submissions: Collection<int, Datum>, cancelled_submissions: Collection<int, Datum>, evaluators: Collection<int, array{type: string, name: string}>}
     */
    private function row(
        Criterion $criterion,
        int $sectionNumber,
        string $state,
        ?float $point,
        int $pendingCount,
        Collection $acceptedSubmissions,
        Collection $cancelledSubmissions,
        Collection $evaluators,
        array $ratingMethod,
    ): array {
        return [
            'criterion' => $criterion,
            'code' => $criterion->code ?: "{$sectionNumber}/{$criterion->getKey()}",
            'state' => $state,
            'point' => $point,
            'pending_count' => $pendingCount,
            'accepted_submissions' => $acceptedSubmissions,
            'cancelled_submissions' => $cancelledSubmissions,
            'evaluators' => $evaluators,
            'rating_method' => $ratingMethod,
        ];
    }

    /** @return array{key: string, label: string, explanation: string, note: string, example: string, maximum: float|null} */
    private function ratingMethod(Criterion $criterion, string $evaluationCategory): array
    {
        $evaluation = $criterion->criterionEvaluations->firstWhere('evaluation', $evaluationCategory);
        $maximum = $evaluation?->has === '1' ? max(0, (float) $evaluation->score) : null;
        $exampleMaximum = $maximum ?? 5;
        $formattedMaximum = number_format($exampleMaximum, 2, '.', '');

        if ($criterion->isHIndexCriterion()) {
            return [
                'key' => 'h-index',
                'label' => 'H-index bo‘yicha',
                'explanation' => 'Kiritilgan har bir platformadagi H-index alohida hisoblanadi va olingan ballar qo‘shiladi.',
                'note' => 'Faqat linki va H-index qiymati to‘liq kiritilgan platformalar hisobga olinadi. h=3 uchun ulushning 50%, h=4 uchun 75%, h=5 uchun 100% beriladi; 5 dan yuqori har bir birlik yana 1 ball qo‘shadi.',
                'example' => "Toifa ulushi {$formattedMaximum} ball va Scopus h-index 4 bo‘lsa: {$formattedMaximum} × 75% = ".number_format($exampleMaximum * 0.75, 2, '.', '').' ball.',
                'maximum' => $maximum,
            ];
        }

        return match ($criterion->formula?->code) {
            Formula::Competition => [
                'key' => Formula::Competition,
                'label' => 'Raqobat asosida',
                'explanation' => 'Kriteriyadagi eng yuqori xom natija maksimal ballni oladi, qolgan natijalar unga nisbatan mutanosib hisoblanadi.',
                'note' => 'Eng yuqori natija o‘zgarsa, shu kriteriyadagi barcha foydalanuvchilarning yakuniy ballari qayta hisoblanadi.',
                'example' => "Eng yuqori natija 10, foydalanuvchi natijasi 8 va maksimal ball {$formattedMaximum} bo‘lsa: {$formattedMaximum} × 8 ÷ 10 = ".number_format($exampleMaximum * 0.8, 2, '.', '').' ball.',
                'maximum' => $maximum,
            ],
            Formula::Maximum => [
                'key' => Formula::Maximum,
                'label' => 'Maksimal ballgacha',
                'explanation' => 'Tasdiqlangan resurslardan yig‘ilgan xom ball toifa uchun belgilangan maksimal chegaragacha hisoblanadi.',
                'note' => 'Yig‘ilgan xom ball chegaradan oshsa, yakuniy natija maksimal ball bilan cheklanadi.',
                'example' => 'Xom ball '.number_format($exampleMaximum + 2, 2, '.', '')." va maksimal ball {$formattedMaximum} bo‘lsa, yakuniy natija {$formattedMaximum} ball bo‘ladi.",
                'maximum' => $maximum,
            ],
            Formula::Unlimited => [
                'key' => Formula::Unlimited,
                'label' => 'Cheklanmagan yig‘indi',
                'explanation' => 'Barcha tasdiqlangan resurslarning xom ballari qo‘shiladi va yakuniy natijaga to‘liq o‘tadi.',
                'note' => 'Bu usulda kriteriya bo‘yicha umumiy ballga yuqori chegara qo‘yilmaydi.',
                'example' => 'Ikki resurs 2 va 3 ball olsa, yakuniy natija 2 + 3 = 5 ball bo‘ladi.',
                'maximum' => $maximum,
            ],
            default => [
                'key' => 'unconfigured',
                'label' => 'Usul sozlanmagan',
                'explanation' => 'Ushbu kriteriya uchun baholash formulasi biriktirilmagan.',
                'note' => 'Aniq hisoblash usuli administrator tomonidan kriteriya sozlamalarida belgilanadi.',
                'example' => 'Formula belgilanmaguncha yakuniy ballni hisoblash misolini ko‘rsatib bo‘lmaydi.',
                'maximum' => $maximum,
            ],
        };
    }

    /** @return Collection<int, Point> */
    private function points(User $user, ?Report $report): Collection
    {
        if ($report === null) {
            return collect();
        }

        return Point::query()
            ->select(['id', 'user_id', 'criterion_id', 'report_id', 'point'])
            ->whereBelongsTo($user)
            ->whereBelongsTo($report)
            ->orderBy('criterion_id')
            ->get();
    }

    /** @return Collection<int, Collection<int, Datum>> */
    private function submissionsByCriterion(User $user, ?Report $report): Collection
    {
        if ($report === null) {
            return collect();
        }

        return Datum::query()
            ->select(['id', 'user_id', 'criterion_id', 'status', 'point'])
            ->whereBelongsTo($user)
            ->where('status', '!=', 'deleted')
            ->whereHas('criterion', fn (Builder $query): Builder => $query->whereBelongsTo($report))
            ->get()
            ->groupBy('criterion_id');
    }

    /**
     * @param  Collection<int, Point>  $points
     * @return Collection<int, Collection<int, DatumHistory>>
     */
    private function historiesByCriterion(User $user, ?Report $report, Collection $points): Collection
    {
        if ($report === null || $points->isEmpty()) {
            return collect();
        }

        return DatumHistory::query()
            ->select(['id', 'datum_id', 'user_id', 'type', 'message_type'])
            ->where(function (Builder $query): void {
                $query->where('message_type', 'manual_review_approved')
                    ->orWhere(function (Builder $query): void {
                        $query->where('message_type', 'ai_evaluation')
                            ->where('type', 'success');
                    });
            })
            ->whereHas('datum', fn (Builder $query): Builder => $query
                ->whereBelongsTo($user)
                ->where('status', 'accepted')
                ->whereIn('criterion_id', $points->pluck('criterion_id'))
                ->whereHas('criterion', fn (Builder $query): Builder => $query->whereBelongsTo($report)))
            ->with(['datum:id,criterion_id', 'user:id,name'])
            ->get()
            ->groupBy(fn (DatumHistory $history): int => $history->datum->criterion_id);
    }

    /**
     * @param  Collection<int, DatumHistory>  $histories
     * @return Collection<int, array{type: string, name: string}>
     */
    private function evaluators(Criterion $criterion, Collection $histories): Collection
    {
        $evaluators = $histories->map(function (DatumHistory $history) use ($criterion): array {
            if ($history->message_type === 'ai_evaluation') {
                return $this->aiEvaluator($criterion);
            }

            return [
                'type' => 'manual',
                'name' => $history->user?->full ?: ($history->user?->short ?: 'Noma’lum baholovchi'),
            ];
        })->unique(fn (array $evaluator): string => $evaluator['type'].'|'.$evaluator['name'])->values();

        if ($evaluators->isNotEmpty()) {
            return $evaluators;
        }

        if ($criterion->checking === 'ai') {
            return collect([$this->aiEvaluator($criterion)]);
        }

        return collect([['type' => 'unknown', 'name' => 'Auditda qayd etilmagan']]);
    }

    /** @return array{type: string, name: string} */
    private function aiEvaluator(Criterion $criterion): array
    {
        return [
            'type' => 'ai',
            'name' => 'Sun’iy intellekt tomonidan baholangan',
        ];
    }
}
