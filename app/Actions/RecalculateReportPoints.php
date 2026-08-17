<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Services\HIndexScoreCalculator;
use App\Services\IndustryFundingScoreCalculator;
use App\Services\OakArticleScoreCalculator;
use App\Services\PrintedEducationalLiteratureScoreCalculator;
use App\Support\EducationalContentCriterionRule;
use App\Support\ForeignLanguageCertificateCriterionRule;
use App\Support\IndustryFundingCriterionRule;
use App\Support\LaboratoryWorkCriterionRule;
use App\Support\OakArticleCriterionRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class RecalculateReportPoints
{
    public function __construct(
        private OakArticleScoreCalculator $oakArticleScoreCalculator,
        private PrintedEducationalLiteratureScoreCalculator $printedLiteratureScoreCalculator,
        private IndustryFundingScoreCalculator $industryFundingScoreCalculator,
        private HIndexScoreCalculator $hIndexScoreCalculator,
    ) {}

    public function handle(Report $report): void
    {
        Cache::lock("reports:{$report->getKey()}:points-rebuild", 120)
            ->block(5, function () use ($report): void {
                DB::transaction(function () use ($report): void {
                    Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();

                    $this->normalizeCriterionOneNineResourcePoints($report);
                    $this->refreshEducationalContentDatumPoints($report);
                    $this->refreshForeignLanguageCertificateDatumPoints($report);
                    $this->refreshDegreeBasedArticleDatumPoints($report);
                    $this->refreshPrintedLiteratureDatumPoints($report);
                    $this->refreshIndustryFundingDatumPoints($report);
                    $this->refreshLaboratoryWorkDatumPoints($report);
                    $this->refreshHIndexDatumPoints($report);
                    $this->rebuildCriterionPoints($report);
                    $this->rebuildFinalPoints($report);
                }, attempts: 5);
            });
    }

    private function normalizeCriterionOneNineResourcePoints(Report $report): void
    {
        Datum::query()
            ->where('status', 'accepted')
            ->where('point', '!=', 1)
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->where('code', Criterion::RESOURCE_COUNT_COMPETITION_CODE))
            ->lockForUpdate()
            ->lazyById(200)
            ->each(function (Datum $datum): void {
                if ($datum->point === 1.0) {
                    return;
                }

                $oldPoint = $datum->point;
                $datum->update(['point' => 1]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '1.9 mezoni bo‘yicha accepted resurs balli 1 ballga tenglandi. '
                    .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'.',
                    'message_type' => 'criterion_1_9_resource_point_normalized',
                ]);
            });
    }

    private function refreshHIndexDatumPoints(Report $report): void
    {
        Datum::query()
            ->where('status', 'accepted')
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->where('code', Criterion::H_INDEX_CODE))
            ->with(['criterion.criterionEvaluations', 'user'])
            ->lockForUpdate()
            ->get()
            ->each(function (Datum $datum): void {
                $maximumShare = $datum->criterion?->criterionEvaluations
                    ->firstWhere('evaluation', $datum->user?->degree)?->score;

                if (! is_numeric($maximumShare)) {
                    return;
                }

                $calculation = $this->hIndexScoreCalculator->calculate(
                    $datum->hIndexProfiles(),
                    max(0, (float) $maximumShare),
                );

                if (abs($datum->point - $calculation['total']) < 0.00005) {
                    return;
                }

                $oldPoint = $datum->point;
                $message = '3.1.4 H-index balli yangi qoida bo‘yicha qayta hisoblandi. Oldingi ball: '
                    .number_format($oldPoint, 2, '.', '').'. '.$calculation['summary'];

                $datum->update([
                    'point' => $calculation['total'],
                    'reason' => $message,
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => $message,
                    'message_type' => 'h_index_score_recalculated',
                ]);
            });
    }

    private function refreshLaboratoryWorkDatumPoints(Report $report): void
    {
        Datum::query()
            ->where('status', 'accepted')
            ->whereNotNull('author_count')
            ->whereHas('user', fn ($query) => $query->active())
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->where('code', LaboratoryWorkCriterionRule::CODE))
            ->lockForUpdate()
            ->get()
            ->each(function (Datum $datum): void {
                if ($datum->author_count === null) {
                    return;
                }

                $point = LaboratoryWorkCriterionRule::pointForAuthorCount($datum->author_count);

                if ($point <= 0 || abs($datum->point - $point) < 0.00005) {
                    return;
                }

                $oldPoint = $datum->point;
                $datum->update(['point' => $point]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '1.8 balli saqlangan mualliflar soni bo‘yicha qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                        .'Hisob: 0.5 / '.$datum->author_count.' muallif = '
                        .number_format($point, 4, '.', '').' ball.',
                    'message_type' => 'criterion_1_8_point_recalculated',
                ]);
            });
    }

    private function refreshEducationalContentDatumPoints(Report $report): void
    {
        Datum::query()
            ->where('status', 'accepted')
            ->whereNotNull('manual_score_option_id')
            ->whereHas('user', fn ($query) => $query->active())
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->where('code', EducationalContentCriterionRule::CODE))
            ->with([
                'user:id,degree',
                'manualScoreOption:id,criterion_id,code',
                'criterion:id,code',
                'criterion.criterionEvaluations:id,criterion_id,evaluation,has,score',
            ])
            ->lockForUpdate()
            ->get()
            ->each(function (Datum $datum): void {
                $evaluation = $datum->criterion?->criterionEvaluations
                    ->firstWhere('evaluation', $datum->user?->degree);
                $point = $datum->manualScoreOption === null || $evaluation?->has !== '1'
                    ? null
                    : EducationalContentCriterionRule::pointFor(
                        (float) $evaluation->score,
                        $datum->manualScoreOption->code,
                    );

                if ($point === null || abs($datum->point - $point) < 0.00005) {
                    return;
                }

                $oldPoint = $datum->point;
                $datum->update(['point' => $point]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '1.1 balli saqlangan resurs turi va foydalanuvchining joriy toifasi bo‘yicha qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                        .'Yangi ball: '.number_format($point, 4, '.', '').'.',
                    'message_type' => 'criterion_1_1_point_recalculated',
                ]);
            });
    }

    private function refreshForeignLanguageCertificateDatumPoints(Report $report): void
    {
        Datum::query()
            ->where('status', 'accepted')
            ->whereNotNull('manual_score_option_id')
            ->whereHas('user', fn ($query) => $query->active())
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->where('code', ForeignLanguageCertificateCriterionRule::CODE))
            ->with([
                'user:id,degree',
                'user.ratingWorkplace.department:id,parent_id',
                'manualScoreOption:id,criterion_id,code',
            ])
            ->lockForUpdate()
            ->get()
            ->each(function (Datum $datum): void {
                $department = $datum->user?->ratingWorkplace?->department;
                $point = $datum->manualScoreOption === null
                    ? null
                    : ForeignLanguageCertificateCriterionRule::pointFor(
                        $datum->manualScoreOption->code,
                        (string) $datum->user?->degree,
                        $department?->getKey(),
                        $department?->parent_id,
                    );

                if ($point === null || abs($datum->point - $point) < 0.00005) {
                    return;
                }

                $oldPoint = $datum->point;
                $datum->update(['point' => $point]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '2.1.3 balli saqlangan sertifikat darajasi, kafedra va ilmiy toifa bo‘yicha qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                        .'Yangi ball: '.number_format($point, 4, '.', '').'.',
                    'message_type' => 'foreign_language_point_recalculated',
                ]);
            });
    }

    private function refreshIndustryFundingDatumPoints(Report $report): void
    {
        Datum::query()
            ->where('status', 'accepted')
            ->whereHas('user', fn ($query) => $query->active())
            ->whereNotNull('received_amount')
            ->whereNotNull('author_count')
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->where('code', IndustryFundingCriterionRule::CODE))
            ->lockForUpdate()
            ->get()
            ->each(function (Datum $datum): void {
                if ($datum->received_amount === null || $datum->author_count === null) {
                    return;
                }

                $point = $this->industryFundingScoreCalculator->calculate(
                    (float) $datum->received_amount,
                    $datum->author_count,
                );

                if (abs($datum->point - $point) < 0.00005) {
                    return;
                }

                $oldPoint = $datum->point;
                $datum->update(['point' => $point]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '3.1.13 balli saqlangan summa va hammualliflar soni bo‘yicha qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                        .'Yangi ball: '.number_format($point, 4, '.', '').'.',
                    'message_type' => 'industry_funding_point_recalculated',
                ]);
            });
    }

    private function refreshPrintedLiteratureDatumPoints(Report $report): void
    {
        Datum::query()
            ->where('status', 'accepted')
            ->whereHas('user', fn ($query) => $query->active())
            ->whereNotNull('page_count')
            ->whereNotNull('author_count')
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->whereIn('code', Criterion::PRINTED_EDUCATIONAL_LITERATURE_CODES))
            ->with('criterion:id,code')
            ->lockForUpdate()
            ->get()
            ->each(function (Datum $datum): void {
                if ($datum->criterion === null
                    || $datum->page_count === null
                    || $datum->author_count === null) {
                    return;
                }

                $point = $this->printedLiteratureScoreCalculator->calculate(
                    (string) $datum->criterion->code,
                    $datum->page_count,
                    $datum->author_count,
                );

                if (abs($datum->point - $point) < 0.00005) {
                    return;
                }

                $oldPoint = $datum->point;
                $datum->update(['point' => $point]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => $datum->criterion->code.' balli saqlangan sahifa va mualliflar soni bo\'yicha qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                        .'Yangi ball: '.number_format($point, 4, '.', '').'.',
                    'message_type' => 'printed_literature_point_recalculated',
                ]);
            });
    }

    private function refreshDegreeBasedArticleDatumPoints(Report $report): void
    {
        Datum::query()
            ->where('status', 'accepted')
            ->whereBetween('author_count', [1, 1000])
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->whereIn('code', [
                    OakArticleCriterionRule::CODE,
                    Criterion::IMPACT_FACTOR_AI_HUMAN_REVIEW_CODE,
                ]))
            ->with(['criterion:id,code', 'user:id,degree'])
            ->lockForUpdate()
            ->get()
            ->each(function (Datum $datum): void {
                if ($datum->user === null || $datum->author_count === null) {
                    return;
                }

                $point = $this->oakArticleScoreCalculator->calculate(
                    $datum->user->degree,
                    $datum->author_count,
                );

                if (abs($datum->point - $point) < 0.00005) {
                    return;
                }

                $oldPoint = $datum->point;
                $datum->update(['point' => $point]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => $datum->criterion->code.' balli saqlangan mualliflar soni va foydalanuvchining ilmiy darajasi bo‘yicha qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. '
                        .'Yangi ball: '.number_format($point, 4, '.', '').'. '
                        .'Mualliflar soni: '.$datum->author_count.'.',
                    'message_type' => $datum->criterion->isOakArticleCriterion()
                        ? 'oak_article_point_recalculated'
                        : 'criterion_3_1_2_point_recalculated',
                ]);
            });
    }

    private function rebuildCriterionPoints(Report $report): void
    {
        $aggregates = Datum::query()
            ->select(['user_id', 'criterion_id'])
            ->selectRaw('SUM(point) as point')
            ->selectRaw('COUNT(*) as files')
            ->where('status', 'accepted')
            ->whereHas('user', fn ($query) => $query->active())
            ->whereHas('criterion', fn ($query) => $query
                ->whereBelongsTo($report)
                ->whereNotNull('parent_id')
                ->ratingEnabled())
            ->groupBy('user_id', 'criterion_id')
            ->get();

        CriterionPoint::query()->where('report_id', $report->getKey())->delete();

        $rows = $aggregates->map(fn (Datum $aggregate): array => [
            'user_id' => $aggregate->user_id,
            'criterion_id' => $aggregate->criterion_id,
            'report_id' => $report->getKey(),
            'point' => max(0, (float) $aggregate->point),
            'files' => (int) $aggregate->files,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows !== []) {
            CriterionPoint::query()->upsert(
                $rows,
                ['report_id', 'user_id', 'criterion_id'],
                ['point', 'files', 'updated_at'],
            );
        }
    }

    private function rebuildFinalPoints(Report $report): void
    {
        $criteria = Criterion::query()
            ->whereBelongsTo($report)
            ->whereNotNull('parent_id')
            ->ratingEnabled()
            ->with([
                'criterionEvaluations:id,criterion_id,evaluation,has,score',
                'formula:id,code',
                'reviewerAssignment:id,criterion_id,criterion_code',
                'criterionPoints' => fn ($query) => $query
                    ->where('report_id', $report->getKey())
                    ->with('user:id,degree'),
            ])
            ->get();

        Point::query()->where('report_id', $report->getKey())->delete();

        $rows = $criteria->flatMap(fn (Criterion $criterion): Collection => $this->pointRows($report, $criterion))->all();

        if ($rows !== []) {
            Point::query()->upsert(
                $rows,
                ['report_id', 'user_id', 'criterion_id'],
                ['point', 'updated_at'],
            );
        }
    }

    /** @return Collection<int, array<string, int|float|Carbon>> */
    private function pointRows(Report $report, Criterion $criterion): Collection
    {
        $highestRawPoint = max(0, (float) $criterion->criterionPoints->max('point'));

        return $criterion->criterionPoints
            ->filter(fn (CriterionPoint $criterionPoint): bool => $criterionPoint->user !== null)
            ->map(function (CriterionPoint $criterionPoint) use ($report, $criterion, $highestRawPoint): array {
                $evaluation = $criterion->criterionEvaluations
                    ->firstWhere('evaluation', $criterionPoint->user->degree);
                $maximumPoint = $evaluation?->has === '1' ? max(0, (float) $evaluation->score) : 0;
                $rawPoint = max(0, (float) $criterionPoint->point);

                $calculatedPoint = $criterion->isHIndexCriterion()
                    ? $rawPoint
                    : match (true) {
                        $criterion->usesFormula(Formula::Competition) => $highestRawPoint > 0
                            ? $maximumPoint * ($rawPoint / $highestRawPoint)
                            : 0,
                        $criterion->usesFormula(Formula::Maximum) => min($rawPoint, $maximumPoint),
                        $criterion->usesFormula(Formula::Unlimited) => $rawPoint,
                        default => throw new UnexpectedValueException(
                            "Unknown scoring formula [{$criterion->formula?->code}] for criterion [{$criterion->getKey()}].",
                        ),
                    };

                return [
                    'user_id' => $criterionPoint->user_id,
                    'criterion_id' => $criterion->getKey(),
                    'report_id' => $report->getKey(),
                    'point' => round($calculatedPoint, 4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });
    }
}
