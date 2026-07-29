<?php

namespace App\Console\Commands;

use App\Models\Datum;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AssignPendingAiHumanReviews extends Command
{
    protected $signature = 'kpi:ai:assign-human-reviews
                            {--limit=0 : Biriktiriladigan maksimum resurslar soni (0 = barchasi)}
                            {--dry-run : Ma’lumotlarni o‘zgartirmasdan nomzodlar sonini ko‘rsatish}';

    protected $description = 'AI inson tekshiruviga qoldirgan resurslarni mezonning HEMIS mas’uliga biriktiradi';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $candidateCount = 0;
        $assignedCount = 0;

        foreach ($this->candidateQuery()->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            $reviewerHemisId = $datum->criterion?->reviewerAssignment?->hemis_id;

            if (! is_numeric($reviewerHemisId) || (int) $reviewerHemisId <= 0) {
                continue;
            }

            $reviewerHemisId = (int) $reviewerHemisId;

            if (! $this->shouldAssign($datum, $reviewerHemisId)) {
                continue;
            }

            $candidateCount++;

            if ($dryRun) {
                continue;
            }

            if ($limit > 0 && $assignedCount >= $limit) {
                break;
            }

            if ($this->assign($datum->getKey())) {
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

    private function candidateQuery(): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.criterion_id', 'data.reviewer_hemis_id'])
            ->with('criterion.reviewerAssignment:id,criterion_id,hemis_id')
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
    }

    private function shouldAssign(Datum $datum, int $reviewerHemisId): bool
    {
        return (int) ($datum->last_ai_evaluation_id ?? 0)
            > (int) ($datum->last_criterion_transfer_id ?? 0)
            && (int) $datum->reviewer_hemis_id !== $reviewerHemisId;
    }

    private function assign(int $datumId): bool
    {
        return DB::transaction(function () use ($datumId): bool {
            $datum = Datum::query()
                ->with('criterion.reviewerAssignment:id,criterion_id,hemis_id')
                ->lockForUpdate()
                ->find($datumId);
            $reviewerHemisId = $datum?->criterion?->reviewerAssignment?->hemis_id;

            if ($datum === null
                || $datum->status !== 'checking'
                || $datum->criterion?->checking !== 'ai'
                || ! is_numeric($reviewerHemisId)
                || (int) $reviewerHemisId <= 0
                || (int) $datum->reviewer_hemis_id === (int) $reviewerHemisId) {
                return false;
            }

            $reviewerHemisId = (int) $reviewerHemisId;
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
