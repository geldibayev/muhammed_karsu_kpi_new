<?php

namespace App\Jobs;

use App\Models\Datum;
use App\Services\AiSubmissionEvaluator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAiDatumEvaluation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public int $datumId)
    {
        $this->onQueue('ai-evaluations');
    }

    public function handle(AiSubmissionEvaluator $evaluator): void
    {
        $datum = Datum::query()
            ->with(['criterion.criterionEvaluations', 'user'])
            ->find($this->datumId);

        if ($datum === null || $datum->status !== 'checking' || $datum->criterion?->checking !== 'ai') {
            return;
        }

        $result = $evaluator->evaluate($datum);

        DB::transaction(function () use ($result): void {
            $lockedDatum = Datum::query()->lockForUpdate()->find($this->datumId);

            if ($lockedDatum === null || $lockedDatum->status !== 'checking') {
                return;
            }

            $lockedDatum->update([
                'status' => $result->status,
                'point' => $result->point,
                'reason' => $result->reason,
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
        }, 3);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return (string) $this->datumId;
    }

    public function failed(?Throwable $exception): void
    {
        $reason = $this->failureReason($exception);

        try {
            DB::transaction(function () use ($reason): void {
                $datum = Datum::query()->lockForUpdate()->find($this->datumId);

                if ($datum === null || $datum->status !== 'checking') {
                    return;
                }

                $datum->update(['reason' => $reason]);
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

    private function failureReason(?Throwable $exception): string
    {
        $message = mb_strtolower($exception?->getMessage() ?? '', 'UTF-8');

        return match (true) {
            str_contains($message, '429'),
            str_contains($message, 'quota'),
            str_contains($message, 'rate limit') => 'AI xizmatining so‘rov limiti tugagan. Limit yangilanishi yoki tarif sozlamasi tekshirilishi kerak.',

            str_contains($message, 'timed out'),
            str_contains($message, 'timeout') => 'AI xizmatidan belgilangan vaqt ichida javob kelmadi.',

            str_contains($message, '401'),
            str_contains($message, '403'),
            str_contains($message, 'api key'),
            str_contains($message, 'unauthenticated') => 'AI xizmatiga kirish kaliti yoki ruxsat sozlamasi noto‘g‘ri.',

            str_contains($message, 'connection'),
            str_contains($message, 'could not resolve'),
            str_contains($message, 'network') => 'AI xizmatiga tarmoq orqali ulanib bo‘lmadi.',

            default => 'AI tekshiruvi kutilmagan xato sabab yakunlanmadi. Inson tekshiruvi zarur.',
        };
    }
}
