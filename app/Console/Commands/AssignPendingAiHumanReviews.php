<?php

namespace App\Console\Commands;

use App\Models\AiHumanReviewAssignment;
use App\Models\Datum;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AssignPendingAiHumanReviews extends Command
{
    protected $signature = 'kpi:ai:assign-human-reviews
                            {--limit=0 : Biriktiriladigan maksimum resurslar soni (0 = barchasi)}
                            {--reassign : Oldin boshqa mas’ulga biriktirilgan resurslarni ham yangi global mas’ulga o‘tkazish}
                            {--dry-run : Ma’lumotlarni o‘zgartirmasdan nomzodlar sonini ko‘rsatish}';

    protected $description = 'AI inson tekshiruviga qoldirgan resurslarni global HEMIS mas’uliga biriktiradi';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $reassign = (bool) $this->option('reassign');
        $dryRun = (bool) $this->option('dry-run');
        $reviewerHemisId = AiHumanReviewAssignment::activeHemisId();
        $candidateCount = 0;
        $assignedCount = 0;

        if ($reviewerHemisId === null) {
            $this->error('Global AI inson tekshiruvchisi sozlanmagan.');

            return self::FAILURE;
        }

        foreach ($this->candidateQuery($reassign)->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            if (! $this->shouldAssign($datum, $reviewerHemisId, $reassign)) {
                continue;
            }

            $candidateCount++;

            if ($dryRun) {
                continue;
            }

            if ($limit > 0 && $assignedCount >= $limit) {
                break;
            }

            if ($this->assign($datum->getKey(), $reviewerHemisId, $reassign)) {
                $assignedCount++;
            }
        }

        if ($dryRun) {
            $this->info("AI inson tekshiruvi uchun biriktiriladigan resurslar: {$candidateCount}");

            return self::SUCCESS;
        }

        $this->info("AI inson tekshiruvi uchun biriktirildi: {$assignedCount}");

        return self::SUCCESS;
    }

    private function candidateQuery(bool $reassign): Builder
    {
        $query = Datum::query()
            ->select(['data.id', 'data.criterion_id', 'data.reviewer_hemis_id'])
            ->withMax([
                'histories as last_ai_evaluation_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'ai_evaluation'),
            ], 'id')
            ->withMax([
                'histories as last_criterion_transfer_id' => fn (Builder $query): Builder => $query
                    ->where('message_type', 'criterion_transferred'),
            ], 'id')
            ->where('status', 'checking')
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query->where('checking', 'ai'),
            )
            ->whereHas(
                'histories',
                fn (Builder $query): Builder => $query->where('message_type', 'ai_evaluation'),
            );

        if (! $reassign) {
            $query->whereNull('reviewer_hemis_id');
        }

        return $query;
    }

    private function shouldAssign(Datum $datum, int $reviewerHemisId, bool $reassign): bool
    {
        return (int) ($datum->last_ai_evaluation_id ?? 0)
            > (int) ($datum->last_criterion_transfer_id ?? 0)
            && ($datum->reviewer_hemis_id === null
                || ($reassign && (int) $datum->reviewer_hemis_id !== $reviewerHemisId));
    }

    private function assign(int $datumId, int $reviewerHemisId, bool $reassign): bool
    {
        return DB::transaction(function () use ($datumId, $reviewerHemisId, $reassign): bool {
            $activeReviewerHemisId = AiHumanReviewAssignment::query()
                ->active()
                ->sharedLock()
                ->value('hemis_id');

            if ((int) $activeReviewerHemisId !== $reviewerHemisId) {
                return false;
            }

            $datum = Datum::query()
                ->with('criterion:id,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'checking'
                || $datum->criterion?->checking !== 'ai'
                || (int) $datum->reviewer_hemis_id === $reviewerHemisId
                || (! $reassign && $datum->reviewer_hemis_id !== null)) {
                return false;
            }

            $history = $datum->histories()
                ->selectRaw("MAX(CASE WHEN message_type = 'ai_evaluation' THEN id ELSE 0 END) AS last_evaluation_id")
                ->selectRaw("MAX(CASE WHEN message_type = 'criterion_transferred' THEN id ELSE 0 END) AS last_transfer_id")
                ->first();

            if ((int) $history?->last_evaluation_id <= (int) $history?->last_transfer_id) {
                return false;
            }

            $datum->update(['reviewer_hemis_id' => $reviewerHemisId]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => "AI inson tekshiruvi HEMIS ID {$reviewerHemisId} mas’ulga biriktirildi.",
                'message_type' => 'ai_human_review_assigned',
            ]);

            return true;
        }, 3);
    }
}
