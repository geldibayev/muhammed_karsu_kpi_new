<?php

namespace App\Console\Commands;

use App\Actions\DescribeAiFailure;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Services\GeminiFileMimeTypeResolver;
use Gemini\Enums\MimeType;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryUnreadableAiImages extends Command
{
    protected $signature = 'kpi:ai:retry-unreadable-images
                            {--limit=0 : Qayta navbatga qo‘yiladigan maksimum resurslar soni (0 = barchasi)}
                            {--dry-run : Ma’lumotlarni o‘zgartirmasdan mos resurslar sonini ko‘rsatish}
                            {--force : Tasdiqlash so‘ramasdan qayta navbatga qo‘yish}';

    protected $description = 'PDF deb yuborilgan JPEG/PNG resurslarni audit bilan AI qayta tekshiruviga qo‘yadi';

    public function handle(
        GeminiFileMimeTypeResolver $mimeTypeResolver,
        DescribeAiFailure $describeAiFailure,
    ): int {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $eligibleCount = $this->eligibleCount($mimeTypeResolver, $limit);

        if ($dryRun) {
            $this->info("Qayta AI tekshiruviga mos JPEG/PNG resurslar: {$eligibleCount}");

            return self::SUCCESS;
        }

        if ($eligibleCount === 0) {
            $this->info('Qayta AI tekshiruviga mos JPEG/PNG resurs topilmadi.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm("{$eligibleCount} ta resursni AI qayta tekshiruviga yuborasizmi?", false)) {
            $this->warn('Qayta navbatga qo‘yish bekor qilindi.');

            return self::SUCCESS;
        }

        $queuedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($this->eligibleImages($mimeTypeResolver, $limit) as $datum) {
            $queuedDatum = $this->markAsQueued($datum->getKey(), $mimeTypeResolver);

            if ($queuedDatum === null) {
                $skippedCount++;

                continue;
            }

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $datum->getKey(),
                    $queuedDatum['criterion_id'],
                )->afterCommit();
                $queuedCount++;
            } catch (Throwable $exception) {
                $failedCount++;
                $this->recordDispatchFailure(
                    $datum->getKey(),
                    $queuedDatum,
                    $describeAiFailure->handle($exception),
                );
                Log::error('Rasm resursi AI qayta tekshiruv navbatiga qo‘yilmadi.', [
                    'datum_id' => $datum->getKey(),
                    'criterion_id' => $queuedDatum['criterion_id'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("AI qayta tekshiruviga qo‘yildi: {$queuedCount}");

        if ($skippedCount > 0) {
            $this->warn("Holati o‘zgargani sabab o‘tkazib yuborildi: {$skippedCount}");
        }

        if ($failedCount > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedCount}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function eligibleCount(
        GeminiFileMimeTypeResolver $mimeTypeResolver,
        int $limit,
    ): int {
        $count = 0;

        foreach ($this->eligibleImages($mimeTypeResolver, $limit) as $datum) {
            $count++;
        }

        return $count;
    }

    /** @return iterable<int, Datum> */
    private function eligibleImages(
        GeminiFileMimeTypeResolver $mimeTypeResolver,
        int $limit,
    ): iterable {
        $eligibleCount = 0;

        foreach ($this->candidateQuery()->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            if (! in_array($mimeTypeResolver->handle($datum), [
                MimeType::IMAGE_JPEG,
                MimeType::IMAGE_PNG,
            ], true)) {
                continue;
            }

            yield $datum;
            $eligibleCount++;

            if ($limit > 0 && $eligibleCount >= $limit) {
                return;
            }
        }
    }

    private function candidateQuery(): Builder
    {
        return Datum::query()
            ->select([
                'data.id',
                'data.user_id',
                'data.criterion_id',
                'data.status',
                'data.reason',
                'data.reviewer_hemis_id',
                'data.material',
            ])
            ->where('data.status', 'checking')
            ->where('data.reason', DescribeAiFailure::DOCUMENT_WITHOUT_PAGES_REASON)
            ->where('data.material->type', 'file')
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query->where('checking', 'ai'),
            );
    }

    /**
     * @return array{criterion_id: int, reviewer_hemis_id: int|null}|null
     */
    private function markAsQueued(
        int $datumId,
        GeminiFileMimeTypeResolver $mimeTypeResolver,
    ): ?array {
        return DB::transaction(function () use ($datumId, $mimeTypeResolver): ?array {
            $datum = Datum::query()
                ->with('criterion:id,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'checking'
                || $datum->reason !== DescribeAiFailure::DOCUMENT_WITHOUT_PAGES_REASON
                || $datum->criterion?->checking !== 'ai'
                || ! in_array($mimeTypeResolver->handle($datum), [
                    MimeType::IMAGE_JPEG,
                    MimeType::IMAGE_PNG,
                ], true)) {
                return null;
            }

            $previousReviewerHemisId = $datum->reviewer_hemis_id;

            $datum->update([
                'status' => 'checking',
                'point' => 0,
                'impact_factor' => null,
                'publication_tier' => null,
                'reason' => 'Rasm fayli AI qayta tekshiruv navbatiga qo‘yildi.',
                'reviewer_hemis_id' => null,
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'info',
                'message' => 'Oldingi fayl turi xatosidan so‘ng rasm resursi AI qayta tekshiruv navbatiga qo‘yildi.',
                'message_type' => 'ai_queued',
            ]);

            return [
                'criterion_id' => $datum->criterion_id,
                'reviewer_hemis_id' => $previousReviewerHemisId,
            ];
        }, 3);
    }

    /**
     * @param  array{criterion_id: int, reviewer_hemis_id: int|null}  $queuedDatum
     */
    private function recordDispatchFailure(
        int $datumId,
        array $queuedDatum,
        string $reason,
    ): void {
        DB::transaction(function () use ($datumId, $queuedDatum, $reason): void {
            $datum = Datum::query()->lockForUpdate()->find($datumId);

            if ($datum === null
                || $datum->criterion_id !== $queuedDatum['criterion_id']
                || $datum->status !== 'checking') {
                return;
            }

            $datum->update([
                'reason' => $reason,
                'reviewer_hemis_id' => $queuedDatum['reviewer_hemis_id'],
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'warning',
                'message' => $reason,
                'message_type' => 'ai_failed',
            ]);
        }, 3);
    }
}
