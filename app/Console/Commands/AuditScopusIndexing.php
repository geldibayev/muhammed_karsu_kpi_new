<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Models\Datum;
use App\Models\Report;
use App\Support\ScopusCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuditScopusIndexing extends Command
{
    private const MAXIMUM_PDF_SIZE = 50 * 1024 * 1024;

    private const REFERENCE_DIRECTORY = 'criterion-3.1.3';

    private const REJECTION_REASON = 'Maqola Scopus bazasida indekslanmagan';

    protected $signature = 'kpi:criteria:audit-3-1-3-indexing
                            {report : Tekshiriladigan hisobot ID raqami}
                            {--apply : PDFda topilmagan accepted resurslarni rad etish}';

    protected $description = '3.1.3 accepted resurslarini yil bo‘yicha Scopus PDF ro‘yxatlari bilan solishtiradi';

    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($reportId === false || ($report = Report::query()->find($reportId)) === null) {
            $this->error('Hisobot topilmadi yoki ID noto‘g‘ri.');

            return self::FAILURE;
        }

        $referenceCorpora = $this->referenceCorpora();

        if ($referenceCorpora === null) {
            return self::FAILURE;
        }

        $referenceCorpus = implode('', $referenceCorpora);

        $candidateCount = 0;
        $indexedCount = 0;
        $notIndexedCount = 0;
        $unsearchableCount = 0;
        $rejectedCount = 0;

        foreach ($this->candidateQuery($report, array_keys($referenceCorpora))
            ->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            $candidateCount++;
            $searchableValues = $this->searchableValues($datum);

            if ($searchableValues === []) {
                $unsearchableCount++;

                continue;
            }

            if ($this->isIndexed($searchableValues, $referenceCorpus)) {
                $indexedCount++;

                continue;
            }

            $notIndexedCount++;

            if ((bool) $this->option('apply')
                && $this->rejectIfStillEligible($datum->getKey(), $report, $referenceCorpus)) {
                $rejectedCount++;
            }
        }

        $this->info("Tekshirilgan accepted resurslar: {$candidateCount}");
        $this->info("Scopus PDFda topildi: {$indexedCount}");
        $this->info("Scopus PDFda topilmadi: {$notIndexedCount}");
        $this->info("Sarlavha yoki DOI yo‘qligi sabab tekshirib bo‘lmadi: {$unsearchableCount}");

        if (! (bool) $this->option('apply')) {
            $this->warn('Dry-run: bazaga o‘zgarish kiritilmadi. Rad etish uchun --apply qo‘shing.');

            return self::SUCCESS;
        }

        if ($rejectedCount > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("Rad etildi: {$rejectedCount}");

        return self::SUCCESS;
    }

    /** @param list<int> $yearIds */
    private function candidateQuery(Report $report, array $yearIds): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.material', 'data.user_id', 'data.criterion_id', 'data.year_id'])
            ->where('data.status', 'accepted')
            ->whereIn('data.year_id', $yearIds)
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->whereBelongsTo($report)
                ->where('code', ScopusCriterionRule::CODE));
    }

    /** @return array<int, string>|null */
    private function referenceCorpora(): ?array
    {
        $disk = Storage::disk('scopus-references');
        $pdfPaths = collect($disk->allFiles(self::REFERENCE_DIRECTORY))
            ->filter(fn (string $path): bool => Str::endsWith(Str::lower($path), '.pdf'))
            ->values();

        if ($pdfPaths->isEmpty()) {
            $this->error('Scopus PDF fayllari topilmadi: '.self::REFERENCE_DIRECTORY);

            return null;
        }

        $corpora = [];

        foreach ($pdfPaths as $pdfPath) {
            $year = Str::before(Str::after($pdfPath, self::REFERENCE_DIRECTORY.'/'), '/');

            if (preg_match('/^\d{4}$/', $year) !== 1
                || $disk->size($pdfPath) > self::MAXIMUM_PDF_SIZE
                || ! in_array($disk->mimeType($pdfPath), ['application/pdf', 'application/x-pdf'], true)) {
                $this->error("Noto‘g‘ri Scopus PDF fayli: {$pdfPath}");

                return null;
            }

            $textPath = Str::beforeLast($pdfPath, '.').'.txt';
            $text = $disk->exists($textPath) ? $this->normalize($disk->get($textPath)) : null;

            if ($text === null || Str::length($text) < 100) {
                $this->error("PDF matnini o‘qib bo‘lmadi: {$pdfPath}");

                return null;
            }

            $yearId = (int) $year;
            $corpora[$yearId] = ($corpora[$yearId] ?? '').$text;
        }

        ksort($corpora);

        return $corpora;
    }

    /** @return list<string> */
    private function searchableValues(Datum $datum): array
    {
        $metadata = $datum->submissionMetadata();
        $title = $this->normalize(data_get($metadata, 'name'));
        $doi = data_get($metadata, 'doi');
        $normalizedDoi = is_string($doi) && preg_match('~10\.\d{4,9}/\S+~iu', $doi, $matches) === 1
            ? $this->normalize(rtrim($matches[0], " \t\n\r\0\x0B.,;)"))
            : null;

        return array_values(array_filter([
            $title !== null && Str::length($title) >= 20 ? $title : null,
            $normalizedDoi,
        ]));
    }

    /** @param list<string> $searchableValues */
    private function isIndexed(array $searchableValues, string $corpus): bool
    {
        return collect($searchableValues)->contains(
            fn (string $searchableValue): bool => Str::contains($corpus, $searchableValue),
        );
    }

    private function rejectIfStillEligible(int $datumId, Report $report, string $corpus): bool
    {
        return DB::transaction(function () use ($datumId, $report, $corpus): bool {
            $datum = Datum::query()
                ->whereKey($datumId)
                ->where('status', 'accepted')
                ->whereHas('criterion', fn (Builder $query): Builder => $query
                    ->whereBelongsTo($report)
                    ->where('code', ScopusCriterionRule::CODE))
                ->lockForUpdate()
                ->first();

            if ($datum === null
                || $this->isIndexed($this->searchableValues($datum), $corpus)) {
                return false;
            }

            $datum->update([
                'status' => 'cancelled',
                'point' => 0,
                'author_count' => null,
                'page_count' => null,
                'impact_factor' => null,
                'publication_tier' => null,
                'university_tier' => null,
                'received_amount' => null,
                'reviewer_hemis_id' => null,
                'reason' => self::REJECTION_REASON,
            ]);
            $datum->histories()->create([
                'user_id' => $datum->user_id,
                'type' => 'error',
                'message' => self::REJECTION_REASON,
                'message_type' => 'scopus_index_reference_rejected',
            ]);

            return true;
        }, 3);
    }

    private function normalize(mixed $value): ?string
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($value)));

        return filled($normalized) ? $normalized : null;
    }
}
