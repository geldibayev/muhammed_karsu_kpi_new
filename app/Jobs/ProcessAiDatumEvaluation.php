<?php

namespace App\Jobs;

use App\Actions\DescribeAiFailure;
use App\Actions\RecalculateReportPoints;
use App\Models\AiHumanReviewAssignment;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Services\AiSubmissionEvaluator;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAiDatumEvaluation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const QUEUE = 'ai-evaluations';

    public int $tries = 0;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public int $maxExceptions = 3;

    public function __construct(
        public int $datumId,
        public ?int $criterionId = null,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(
        AiSubmissionEvaluator $evaluator,
        RecalculateReportPoints $recalculateReportPoints,
    ): void {
        $datum = Datum::query()
            ->with([
                'criterion.criterionEvaluations',
                'criterion.report',
                'user',
            ])
            ->find($this->datumId);
        $expectedCriterionId = $this->criterionId ?? $datum?->criterion_id;

        if ($datum === null
            || $datum->criterion?->checking !== 'ai'
            || $datum->criterion_id !== $expectedCriterionId) {
            return;
        }

        if ($datum->status !== 'checking') {
            if (in_array($datum->status, ['accepted', 'cancelled'], true)
                && $datum->histories()->where('message_type', 'ai_evaluation')->exists()) {
                $recalculateReportPoints->handle($datum->criterion->report);
            }

            return;
        }

        $oldestWaitingDatum = $this->oldestWaitingDatum();

        if ($oldestWaitingDatum !== null && ! $oldestWaitingDatum->is($datum)) {
            self::dispatch($oldestWaitingDatum->getKey(), $oldestWaitingDatum->criterion_id);
            $this->release(1);

            return;
        }

        try {
            $result = $evaluator->evaluate($datum);
        } catch (Throwable $exception) {
            Cache::put(
                'kpi:ai-worker:last-failure-datum-id',
                $this->datumId,
                now()->addDays(30),
            );

            throw $exception;
        }

        $resultPersisted = DB::transaction(function () use (
            $expectedCriterionId,
            $result,
        ): bool {
            $lockedDatum = Datum::query()->lockForUpdate()->find($this->datumId);

            if ($lockedDatum === null
                || $lockedDatum->status !== 'checking'
                || $lockedDatum->criterion_id !== $expectedCriterionId) {
                return false;
            }

            $reviewerHemisId = $result->status === 'checking'
                ? AiHumanReviewAssignment::query()
                    ->active()
                    ->sharedLock()
                    ->value('hemis_id')
                : null;
            $reviewerHemisId = is_numeric($reviewerHemisId) ? (int) $reviewerHemisId : null;

            $lockedDatum->update([
                'status' => $result->status,
                'point' => $result->point,
                'reason' => $result->reason,
                'reviewer_hemis_id' => $reviewerHemisId,
            ]);

            $lockedDatum->histories()->create([
                'user_id' => $lockedDatum->user_id,
                'type' => match ($result->status) {
                    'accepted' => 'success',
                    'cancelled' => 'error',
                    default => 'warning',
                },
                'message' => $result->reason,
                'message_type' => 'ai_evaluation',
            ]);

            if ($reviewerHemisId !== null) {
                $lockedDatum->histories()->create([
                    'user_id' => $lockedDatum->user_id,
                    'type' => 'info',
                    'message' => "AI inson tekshiruvi HEMIS ID {$reviewerHemisId} mas’ulga biriktirildi.",
                    'message_type' => 'ai_human_review_assigned',
                ]);
            } elseif ($result->status === 'checking') {
                $lockedDatum->histories()->create([
                    'user_id' => $lockedDatum->user_id,
                    'type' => 'warning',
                    'message' => 'AI inson tekshiruvchisi hali sozlanmagan.',
                    'message_type' => 'ai_human_review_unassigned',
                ]);
            }

            return true;
        }, 3);

        if ($resultPersisted) {
            Cache::put('kpi:ai-worker:last-success-at', now()->toIso8601String(), now()->addDays(30));

            if (in_array($result->status, ['accepted', 'cancelled'], true)) {
                $recalculateReportPoints->handle($datum->criterion->report);
            }
        }
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(self::QUEUE, 1, $this->timeout + 30))->shared(),
            new RateLimited('gemini-api'),
        ];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(7);
    }

    public function uniqueId(): string
    {
        return (string) $this->datumId;
    }

    private function oldestWaitingDatum(): ?Datum
    {
        $aiHistorySummary = DatumHistory::query()
            ->select('datum_id')
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN id ELSE 0 END) AS last_evaluation_id")
            ->selectRaw("MAX(CASE WHEN message_type = 'ai_failed' THEN id ELSE 0 END) AS last_failure_id")
            ->selectRaw("MAX(CASE WHEN message_type IN ('submission_created', 'ai_queued') THEN id ELSE 0 END) AS last_queue_id")
            ->whereIn('message_type', ['ai_evaluation', 'ai_failed', 'submission_created', 'ai_queued'])
            ->groupBy('datum_id');

        return Datum::query()
            ->select(['data.id', 'data.criterion_id'])
            ->join('criteria', 'criteria.id', '=', 'data.criterion_id')
            ->joinSub($aiHistorySummary->toBase(), 'ai_history', function (Builder $join): void {
                $join->on('ai_history.datum_id', '=', 'data.id');
            })
            ->where('criteria.checking', 'ai')
            ->whereIn('data.status', ['received', 'checking'])
            ->whereRaw('ai_history.last_queue_id > ai_history.last_evaluation_id')
            ->whereRaw('ai_history.last_queue_id > ai_history.last_failure_id')
            ->orderBy('ai_history.last_queue_id')
            ->orderBy('data.id')
            ->first();
    }

    public function failed(?Throwable $exception): void
    {
        $reason = app(DescribeAiFailure::class)->handle($exception);

        try {
            DB::transaction(function () use ($reason): void {
                $datum = Datum::query()->lockForUpdate()->find($this->datumId);

                if ($datum === null
                    || $datum->status !== 'checking'
                    || ($this->criterionId !== null && $datum->criterion_id !== $this->criterionId)) {
                    return;
                }

                $datum->update([
                    'reason' => $reason,
                    'reviewer_hemis_id' => null,
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'warning',
                    'message' => $reason,
                    'message_type' => 'ai_failed',
                ]);
            }, 3);
        } catch (Throwable $historyException) {
            Log::error('AI job xatoligi tarixga yozilmadi.', [
                'datum_id' => $this->datumId,
                'job_exception' => $exception?->getMessage(),
                'history_exception' => $historyException->getMessage(),
            ]);
        }
    }
}
