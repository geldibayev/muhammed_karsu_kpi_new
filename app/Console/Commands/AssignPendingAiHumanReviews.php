<?php

namespace App\Console\Commands;

use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\Datum;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AssignPendingAiHumanReviews extends Command
{
    protected $signature = 'kpi:ai:assign-human-reviews
                            {--limit=0 : Biriktiriladigan maksimum resurslar soni (0 = barchasi)}
                            {--criterion= : Faqat ko‘rsatilgan kriteriya kodi bo‘yicha biriktirish}
                            {--reassign : Oldin boshqa mas’ulga biriktirilgan resurslarni ham amaldagi mas’ulga o‘tkazish}
                            {--include-queued : AI javobini kutayotgan resurslarni ham amaldagi mas’ulga o‘tkazish}
                            {--dry-run : Ma’lumotlarni o‘zgartirmasdan nomzodlar sonini ko‘rsatish}';

    protected $description = 'AI inson tekshiruviga qoldirgan resurslarni kriteriya yoki global HEMIS mas’uliga biriktiradi';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $reassign = (bool) $this->option('reassign');
        $includeQueued = (bool) $this->option('include-queued');
        $dryRun = (bool) $this->option('dry-run');
        $criterionCode = trim((string) $this->option('criterion')) ?: null;
        $unassignedCheckingCount = Datum::query()
            ->whereNull('reviewer_hemis_id')
            ->where('status', 'checking')
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query
                    ->where('checking', 'ai')
                    ->when(
                        $criterionCode !== null,
                        fn (Builder $query): Builder => $query->where('code', $criterionCode),
                    ),
            )
            ->count();
        $candidateCount = 0;
        $assignedCount = 0;
        $unassignedCount = 0;

        foreach ($this->candidateQuery($reassign, $criterionCode, $includeQueued)->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            $reviewerHemisId = $datum->criterion instanceof Criterion
                ? AiHumanReviewAssignment::reviewerHemisIdFor($datum->criterion)
                : null;

            if ($reviewerHemisId === null) {
                $unassignedCount++;

                continue;
            }

            if (! $this->shouldAssign($datum, $reviewerHemisId, $reassign, $includeQueued)) {
                continue;
            }

            $candidateCount++;

            if ($dryRun) {
                continue;
            }

            if ($limit > 0 && $assignedCount >= $limit) {
                break;
            }

            if ($this->assign($datum->getKey(), $reviewerHemisId, $reassign, $includeQueued)) {
                $assignedCount++;
            }
        }

        if ($candidateCount === 0 && $unassignedCount > 0) {
            $this->error('Global AI inson tekshiruvchisi sozlanmagan.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->line("Mas’ulsiz checking AI resurslar jami: {$unassignedCheckingCount}");
            $this->info("AI inson tekshiruvi uchun biriktiriladigan resurslar: {$candidateCount}");

            if ($unassignedCheckingCount > $candidateCount + $unassignedCount) {
                $this->warn('Qolgan resurslar AI javobini kutmoqda, qayta navbatga qo‘yilgan yoki kriteriyasi ko‘chirilgan.');
            }

            return self::SUCCESS;
        }

        $this->info("AI inson tekshiruvi uchun biriktirildi: {$assignedCount}");

        return self::SUCCESS;
    }

    private function candidateQuery(bool $reassign, ?string $criterionCode, bool $includeQueued): Builder
    {
        $query = Datum::query()
            ->select(['data.id', 'data.criterion_id', 'data.reviewer_hemis_id'])
            ->with('criterion:id,code,checking')
            ->withMax([
                'histories as last_ai_evaluation_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_evaluation'),
            ], 'id')
            ->withMax([
                'histories as last_criterion_transfer_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'criterion_transferred'),
            ], 'id')
            ->withMax([
                'histories as last_ai_queue_id' => fn (Builder $query): Builder => $query
                    ->whereIn('message_type', ['submission_created', 'ai_queued']),
            ], 'id')
            ->withMax([
                'histories as last_ai_failure_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_failed'),
            ], 'id')
            ->where('status', 'checking')
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query
                    ->where('checking', 'ai')
                    ->when(
                        $criterionCode !== null,
                        fn (Builder $query): Builder => $query->where('code', $criterionCode),
                    ),
            )
            ->whereHas(
                'histories',
                fn (Builder $query): Builder => $query
                    ->whereIn(
                        'message_type',
                        $includeQueued
                            ? ['ai_evaluation', 'ai_failed', 'submission_created', 'ai_queued']
                            : ['ai_evaluation', 'ai_failed'],
                    ),
            );

        if (! $reassign) {
            $query->whereNull('reviewer_hemis_id');
        }

        return $query;
    }

    private function shouldAssign(Datum $datum, int $reviewerHemisId, bool $reassign, bool $includeQueued): bool
    {
        $lastHumanReviewId = max(
            (int) ($datum->last_ai_evaluation_id ?? 0),
            (int) ($datum->last_ai_failure_id ?? 0),
        );

        return $this->latestAttemptCanBeAssigned(
            $lastHumanReviewId,
            (int) ($datum->last_ai_queue_id ?? 0),
            (int) ($datum->last_criterion_transfer_id ?? 0),
            $includeQueued,
        )
            && ($datum->reviewer_hemis_id === null
                || ($reassign && (int) $datum->reviewer_hemis_id !== $reviewerHemisId));
    }

    private function assign(int $datumId, int $reviewerHemisId, bool $reassign, bool $includeQueued): bool
    {
        return DB::transaction(function () use ($datumId, $reviewerHemisId, $reassign, $includeQueued): bool {
            $datum = Datum::query()
                ->with('criterion:id,code,checking')
                ->lockForUpdate()
                ->find($datumId);

            $activeReviewerHemisId = $datum?->criterion instanceof Criterion
                ? AiHumanReviewAssignment::reviewerHemisIdFor(
                    $datum->criterion,
                    sharedLock: true,
                )
                : null;

            if ($datum === null
                || $activeReviewerHemisId !== $reviewerHemisId
                || $datum->status !== 'checking'
                || $datum->criterion?->checking !== 'ai'
                || (int) $datum->reviewer_hemis_id === $reviewerHemisId
                || (! $reassign && $datum->reviewer_hemis_id !== null)) {
                return false;
            }

            $history = $datum->histories()
                ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN id ELSE 0 END) AS last_evaluation_id")
                ->selectRaw("MAX(CASE WHEN message_type = 'criterion_transferred' THEN id ELSE 0 END) AS last_transfer_id")
                ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN id ELSE 0 END) AS last_queue_id")
                ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN id ELSE 0 END) AS last_failure_id")
                ->first();

            $lastHumanReviewId = max(
                (int) $history?->last_evaluation_id,
                (int) $history?->last_failure_id,
            );

            if (! $this->latestAttemptCanBeAssigned(
                $lastHumanReviewId,
                (int) $history?->last_queue_id,
                (int) $history?->last_transfer_id,
                $includeQueued,
            )) {
                return false;
            }

            $datum->update([
                'reason' => Datum::PUBLIC_CHECKING_REASON,
                'reviewer_hemis_id' => $reviewerHemisId,
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => "AI inson tekshiruvi HEMIS ID {$reviewerHemisId} mas’ulga biriktirildi.",
                'message_type' => 'ai_human_review_assigned',
            ]);

            return true;
        }, 3);
    }

    private function latestAttemptCanBeAssigned(
        int $lastHumanReviewId,
        int $lastQueueId,
        int $lastTransferId,
        bool $includeQueued,
    ): bool {
        return ($lastHumanReviewId > $lastTransferId && $lastHumanReviewId > $lastQueueId)
            || ($includeQueued && $lastQueueId > $lastTransferId && $lastQueueId > $lastHumanReviewId);
    }
}
