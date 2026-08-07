<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\Report;
use App\Services\ScopusPublicationTierResolver;
use App\Support\ScopusCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecheckScopusAiEvaluations extends Command
{
    protected $signature = 'kpi:criteria:recheck-3-1-3-publications
                            {report : Qayta tekshiriladigan hisobot ID raqami}
                            {--datum=* : Faqat ko‘rsatilgan resurs IDlarini qayta ishlash}
                            {--limit= : Qayta ishlanadigan resurslar sonini cheklash}
                            {--apply : Aniqlanganlarni qayta hisoblash, noaniqlarni AI navbatiga qo‘yish}';

    protected $description = '3.1.3 accepted resurslarini yangi Q1–Q4 va konferensiya ballari bo‘yicha yangilaydi';

    public function handle(
        ScopusPublicationTierResolver $tierResolver,
        RecalculateReportPoints $recalculateReportPoints,
    ): int {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $limit = $this->validatedLimit();
        $datumIds = $this->validatedDatumIds();

        if ($reportId === false || $limit === false || $datumIds === false) {
            if ($reportId === false) {
                $this->error('Hisobot ID musbat butun son bo‘lishi kerak.');
            }

            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        $candidateCount = $this->candidateQuery($report, $datumIds)->count();
        ['resolved' => $resolvedCount, 'unresolved' => $unresolvedCount] = $this->preview(
            $report,
            $datumIds,
            $limit,
            $tierResolver,
        );

        $this->info("3.1.3 bo‘yicha eski accepted resurslar: {$candidateCount}");
        $this->info("Aniq kvartil/konferensiya turi topildi: {$resolvedCount}");
        $this->info("AI qayta tekshiruvi kerak: {$unresolvedCount}");

        if (! (bool) $this->option('apply')) {
            $this->warn('Dry-run: o‘zgarish kiritilmadi. Amalga oshirish uchun --apply qo‘shing.');

            return self::SUCCESS;
        }

        $processedCount = 0;
        $recalculatedCount = 0;
        $queuedCount = 0;
        $failedDispatchCount = 0;

        foreach ($this->candidateQuery($report, $datumIds)->lazyById(200, column: 'data.id', alias: 'id') as $candidate) {
            if ($limit !== null && $processedCount >= $limit) {
                break;
            }

            $result = $this->processCandidate((int) $candidate->getKey(), $report, $tierResolver);

            if ($result === null) {
                continue;
            }

            $processedCount++;

            if ($result['type'] === 'resolved') {
                $recalculatedCount++;

                continue;
            }

            $queuedCount++;

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $result['datum']->getKey(),
                    $result['datum']->criterion_id,
                )->afterCommit();
                $this->recordSuccessfulDispatch($result['datum']->getKey());
            } catch (Throwable $exception) {
                $failedDispatchCount++;
                report($exception);
                $this->restoreAfterDispatchFailure(
                    $result['datum']->getKey(),
                    $result['started_history_id'],
                    $result['original'],
                );
            }
        }

        if ($processedCount > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("Serverda qayta hisoblandi: {$recalculatedCount}");
        $this->info("AI qayta tekshiruviga qo‘yildi: {$queuedCount}");

        if ($failedDispatchCount > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedDispatchCount}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param list<int> $datumIds */
    private function candidateQuery(Report $report, array $datumIds): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id'])
            ->where('data.status', 'accepted')
            ->when($datumIds !== [], fn (Builder $query): Builder => $query->whereKey($datumIds))
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->whereBelongsTo($report)
                ->where('code', ScopusCriterionRule::CODE)
                ->where('checking', 'ai'))
            ->whereDoesntHave('histories', fn (Builder $query): Builder => $query
                ->whereIn('message_type', [
                    'scopus_tier_score_recalculated',
                    'ai_scopus_recheck_queued',
                ]));
    }

    /**
     * @param  list<int>  $datumIds
     * @return array{resolved: int, unresolved: int}
     */
    private function preview(
        Report $report,
        array $datumIds,
        ?int $limit,
        ScopusPublicationTierResolver $tierResolver,
    ): array {
        $resolved = 0;
        $unresolved = 0;
        $processed = 0;

        foreach ($this->candidateQuery($report, $datumIds)
            ->with(['histories' => fn ($query) => $query
                ->whereIn('message_type', ['ai_evaluation', 'manual_review_approved'])])
            ->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }

            $processed++;
            $tierResolver->resolve($datum) === null ? $unresolved++ : $resolved++;
        }

        return compact('resolved', 'unresolved');
    }

    /**
     * @return array{
     *     type: 'resolved'|'queued',
     *     datum: Datum,
     *     original?: array<string, mixed>,
     *     started_history_id?: int
     * }|null
     */
    private function processCandidate(
        int $datumId,
        Report $report,
        ScopusPublicationTierResolver $tierResolver,
    ): ?array {
        return DB::transaction(function () use ($datumId, $report, $tierResolver): ?array {
            $datum = Datum::query()
                ->with([
                    'criterion:id,report_id,code,checking',
                    'histories' => fn ($query) => $query
                        ->whereIn('message_type', ['ai_evaluation', 'manual_review_approved']),
                ])
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'accepted'
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->code !== ScopusCriterionRule::CODE
                || $datum->criterion->checking !== 'ai'
                || $datum->histories()->whereIn('message_type', [
                    'scopus_tier_score_recalculated',
                    'ai_scopus_recheck_queued',
                ])->exists()) {
                return null;
            }

            $publicationTier = $tierResolver->resolve($datum);
            $point = $publicationTier === null ? null : ScopusCriterionRule::pointFor($publicationTier);

            if ($publicationTier !== null && $point !== null) {
                $oldPoint = $datum->point;
                $datum->update([
                    'point' => $point,
                    'publication_tier' => $publicationTier,
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => mb_strtoupper($publicationTier).' tasnifi bo‘yicha ball serverda '
                        .number_format($oldPoint, 2, '.', '').' dan '
                        .number_format($point, 2, '.', '').' ga qayta hisoblandi.',
                    'message_type' => 'scopus_tier_score_recalculated',
                ]);

                return ['type' => 'resolved', 'datum' => $datum];
            }

            $original = $datum->only([
                'status',
                'point',
                'author_count',
                'page_count',
                'impact_factor',
                'publication_tier',
                'university_tier',
                'received_amount',
                'reason',
                'reviewer_hemis_id',
            ]);
            $datum->update([
                'status' => 'checking',
                'point' => 0,
                'author_count' => null,
                'page_count' => null,
                'impact_factor' => null,
                'publication_tier' => null,
                'university_tier' => null,
                'received_amount' => null,
                'reason' => Datum::PUBLIC_CHECKING_REASON,
                'reviewer_hemis_id' => null,
            ]);
            $startedHistory = $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => '3.1.3 resursida aniq kvartil yoki Scopus/WoS konferensiya turi topilmadi. Qat’iy AI qayta tekshiruviga belgilandi.',
                'message_type' => 'ai_scopus_recheck_started',
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => 'Resurs AI tekshiruv navbatiga qo‘yildi.',
                'message_type' => 'ai_queued',
            ]);

            return [
                'type' => 'queued',
                'datum' => $datum,
                'original' => $original,
                'started_history_id' => (int) $startedHistory->getKey(),
            ];
        }, 3);
    }

    private function recordSuccessfulDispatch(int $datumId): void
    {
        DB::transaction(function () use ($datumId): void {
            $datum = Datum::query()->lockForUpdate()->find($datumId);

            if ($datum === null
                || $datum->histories()->where('message_type', 'ai_scopus_recheck_queued')->exists()) {
                return;
            }

            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => '3.1.3 resursi AI qayta tekshiruv navbatiga muvaffaqiyatli yuborildi.',
                'message_type' => 'ai_scopus_recheck_queued',
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $original */
    private function restoreAfterDispatchFailure(
        int $datumId,
        int $startedHistoryId,
        array $original,
    ): void {
        DB::transaction(function () use ($datumId, $startedHistoryId, $original): void {
            $datum = Datum::query()->lockForUpdate()->find($datumId);

            if ($datum === null
                || $datum->status !== 'checking'
                || $datum->histories()
                    ->where('message_type', 'ai_evaluation')
                    ->where('id', '>', $startedHistoryId)
                    ->exists()) {
                return;
            }

            $datum->update($original);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'warning',
                'message' => '3.1.3 resursini AI navbatiga yuborib bo‘lmadi. Oldingi accepted holati tiklandi.',
                'message_type' => 'ai_scopus_recheck_dispatch_failed',
            ]);
        }, 3);
    }

    private function validatedLimit(): int|false|null
    {
        $value = $this->option('limit');

        if ($value === null) {
            return null;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($limit === false) {
            $this->error('--limit musbat butun son bo‘lishi kerak.');
        }

        return $limit;
    }

    /** @return list<int>|false */
    private function validatedDatumIds(): array|false
    {
        $datumIds = [];

        foreach ((array) $this->option('datum') as $datumId) {
            $validatedDatumId = filter_var($datumId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($validatedDatumId === false) {
                $this->error('--datum qiymatlari musbat butun son bo‘lishi kerak.');

                return false;
            }

            $datumIds[] = $validatedDatumId;
        }

        return array_values(array_unique($datumIds));
    }
}
