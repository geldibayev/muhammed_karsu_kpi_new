<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\Report;
use App\Services\PrintedEducationalLiteratureScoreCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecalculatePrintedEducationalLiteraturePoints
{
    public function __construct(
        private PrintedEducationalLiteratureScoreCalculator $scoreCalculator,
        private RecalculateReportPoints $recalculateReportPoints,
    ) {}

    /**
     * @return array{total: int, changes: int, unresolved_ids: array<int, int>, requeued_ids: array<int, int>, conflicts: int, page_sources: array<string, int>, author_sources: array<string, int>}
     */
    public function handle(
        Report $report,
        bool $apply = false,
        bool $requeueUnresolved = false,
    ): array {
        if (! $apply) {
            $analysis = $this->analyse($report);
            $analysis['requeued_ids'] = [];
            unset($analysis['rows']);

            return $analysis;
        }

        $analysis = DB::transaction(function () use ($report, $requeueUnresolved): array {
            Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $analysis = $this->analyse($report, true);

            foreach ($analysis['rows'] as $row) {
                if (! $row['changes']) {
                    continue;
                }

                /** @var Datum $datum */
                $datum = $row['datum'];
                $datum->update([
                    'page_count' => $row['page_count'],
                    'author_count' => $row['author_count'],
                    'point' => $row['point'],
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => $datum->criterion->code.' balli Gemini ishlatilmasdan qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($row['old_point'], 4, '.', '').'. '
                        .'Hisob: '.$row['page_count'].' sahifa / 16 × '
                        .number_format($row['rate'], 1, '.', '').' / '.$row['author_count'].' muallif = '
                        .number_format($row['point'], 4, '.', '').' ball. '
                        .'Sahifalar manbasi: '.$row['page_source'].'. '
                        .'Mualliflar manbasi: '.$row['author_source'].'.',
                    'message_type' => 'printed_literature_rule_recalculated',
                ]);
            }

            $requeuedIds = [];

            if ($requeueUnresolved && $analysis['unresolved_ids'] !== []) {
                $unresolvedData = Datum::query()
                    ->whereIn('id', $analysis['unresolved_ids'])
                    ->lockForUpdate()
                    ->get();

                foreach ($unresolvedData as $datum) {
                    $datum->update([
                        'status' => 'checking',
                        'point' => 0,
                        'author_count' => null,
                        'page_count' => null,
                        'impact_factor' => null,
                        'publication_tier' => null,
                        'reviewer_hemis_id' => null,
                        'reason' => 'AI tahlili navbatga qo\'yildi.',
                    ]);
                    $datum->histories()->createMany([
                        [
                            'user_id' => $datum->user_id,
                            'type' => 'info',
                            'message' => '1.2/1.3 yangi hisoblash qoidasi uchun sahifalar va mualliflar sonini aniqlash maqsadida resurs qayta AI tekshiruviga yuborildi.',
                            'message_type' => 'printed_literature_recheck_queued',
                        ],
                        [
                            'user_id' => $datum->user_id,
                            'type' => 'info',
                            'message' => 'Resurs AI tekshiruv navbatiga qo\'yildi.',
                            'message_type' => 'ai_queued',
                        ],
                    ]);
                    $requeuedIds[] = $datum->getKey();
                }
            }

            $analysis['requeued_ids'] = $requeuedIds;

            unset($analysis['rows']);

            return $analysis;
        }, 3);

        if ($analysis['changes'] > 0 || $analysis['requeued_ids'] !== []) {
            $this->recalculateReportPoints->handle($report);
        }

        return $analysis;
    }

    /**
     * @return array{total: int, changes: int, unresolved_ids: array<int, int>, conflicts: int, page_sources: array<string, int>, author_sources: array<string, int>, rows: Collection<int, array{datum: Datum, page_count: int, author_count: int, point: float, old_point: float, rate: float, page_source: string, author_source: string, changes: bool}>}
     */
    private function analyse(Report $report, bool $lockForUpdate = false): array
    {
        $data = Datum::query()
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->where('report_id', $report->getKey())
                ->where('checking', 'ai')
                ->whereIn('code', ['1.2', '1.3']))
            ->where('status', 'accepted')
            ->with([
                'criterion:id,code,report_id',
                'histories' => fn ($query) => $query
                    ->select(['id', 'datum_id', 'message'])
                    ->whereIn('message_type', ['ai_evaluation', 'printed_literature_rule_recalculated'])
                    ->latest('id'),
            ])
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->orderBy('id')
            ->get();

        $unresolvedIds = [];
        $conflicts = 0;
        $pageSources = [];
        $authorSources = [];
        $rows = collect();

        foreach ($data as $datum) {
            $page = $this->resolvePageCount($datum);
            $author = $this->resolveAuthorCount($datum);

            if ($page === null || $author === null || $datum->criterion === null) {
                $unresolvedIds[] = $datum->getKey();

                continue;
            }

            $conflicts += $page['conflict'] || $author['conflict'] ? 1 : 0;
            $pageSources[$page['source']] = ($pageSources[$page['source']] ?? 0) + 1;
            $authorSources[$author['source']] = ($authorSources[$author['source']] ?? 0) + 1;
            $criterionCode = (string) $datum->criterion->code;
            $rate = $criterionCode === '1.2' ? 0.4 : 0.3;
            $point = $this->scoreCalculator->calculate(
                $criterionCode,
                $page['value'],
                $author['value'],
            );

            $rows->push([
                'datum' => $datum,
                'page_count' => $page['value'],
                'author_count' => $author['value'],
                'point' => $point,
                'old_point' => $datum->point,
                'rate' => $rate,
                'page_source' => $page['source'],
                'author_source' => $author['source'],
                'changes' => $datum->page_count !== $page['value']
                    || $datum->author_count !== $author['value']
                    || abs($datum->point - $point) >= 0.00005,
            ]);
        }

        return [
            'total' => $data->count(),
            'changes' => $rows->where('changes', true)->count(),
            'unresolved_ids' => $unresolvedIds,
            'conflicts' => $conflicts,
            'page_sources' => $pageSources,
            'author_sources' => $authorSources,
            'rows' => $rows,
        ];
    }

    /** @return array{value: int, source: string, conflict: bool}|null */
    private function resolvePageCount(Datum $datum): ?array
    {
        $candidates = [];

        if ($datum->page_count !== null && $datum->page_count >= 1) {
            $candidates['structured'] = $datum->page_count;
        }

        foreach (['page_count', 'pages', 'article.page_count', 'article.pages', 'data.page_count', 'data.pages'] as $key) {
            $value = data_get($datum->material, $key);

            if (is_numeric($value) && (int) $value >= 1 && (int) $value <= 100000) {
                $candidates['material'] = (int) $value;
                break;
            }
        }

        foreach ($datum->histories as $history) {
            $pageCount = $this->pageCountFromText($history->message);

            if ($pageCount !== null) {
                $candidates['ai_history'] = $pageCount;
                break;
            }
        }

        $reasonPageCount = $this->pageCountFromText($datum->reason);

        if ($reasonPageCount !== null) {
            $candidates['reason'] = $reasonPageCount;
        }

        return $this->resolvedCandidate($candidates);
    }

    /** @return array{value: int, source: string, conflict: bool}|null */
    private function resolveAuthorCount(Datum $datum): ?array
    {
        $candidates = [];

        if ($datum->author_count !== null && $datum->author_count >= 1 && $datum->author_count <= 1000) {
            $candidates['structured'] = $datum->author_count;
        }

        foreach (['author_count', 'authors_num', 'article.author_count', 'article.authors_num', 'data.author_count', 'data.authors_num'] as $key) {
            $value = data_get($datum->material, $key);

            if (is_numeric($value) && (int) $value >= 1 && (int) $value <= 1000) {
                $candidates['material'] = (int) $value;
                break;
            }
        }

        foreach ($datum->histories as $history) {
            $authorCount = $this->authorCountFromText($history->message);

            if ($authorCount !== null) {
                $candidates['ai_history'] = $authorCount;
                break;
            }
        }

        $reasonAuthorCount = $this->authorCountFromText($datum->reason);

        if ($reasonAuthorCount !== null) {
            $candidates['reason'] = $reasonAuthorCount;
        }

        return $this->resolvedCandidate($candidates);
    }

    private function pageCountFromText(?string $text): ?int
    {
        if (blank($text)) {
            return null;
        }

        foreach ([
            '/(?:jami\s+)?(?:sahifalar|sahifa|betlar|bet)\s*(?:soni)?\s*[:=\-–—]?\s*(\d{1,6})/iu',
            '/(\d{1,6})\s*(?:ta\s+)?(?:sahifa|bet)\b/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $pageCount = (int) $matches[1];

                if ($pageCount >= 1 && $pageCount <= 100000) {
                    return $pageCount;
                }
            }
        }

        foreach ([
            '/(?:bosma\s+taboq|bosma\s+tabog(?:\'|вЂ|‘)?i)\s*(?:hajmi)?\s*[:=\-–—]?\s*(\d+(?:[.,]\d+)?)/iu',
            '/(\d+(?:[.,]\d+)?)\s*(?:bosma\s+taboq|bosma\s+tabog(?:\'|вЂ|‘)?i)/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            $pageCount = (float) str_replace(',', '.', $matches[1]) * 16;

            if ($pageCount >= 1 && $pageCount <= 100000 && abs($pageCount - round($pageCount)) < 0.0001) {
                return (int) round($pageCount);
            }
        }

        return null;
    }

    private function authorCountFromText(?string $text): ?int
    {
        if (blank($text)) {
            return null;
        }

        foreach ([
            '/(?:jami\s+)?mualliflar\s+soni\s*[:=\-–—]?\s*(\d{1,4})/iu',
            '/(\d{1,4})\s+(?:nafar\s+)?muallif/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $authorCount = (int) $matches[1];

                if ($authorCount >= 1 && $authorCount <= 1000) {
                    return $authorCount;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, int>  $candidates
     * @return array{value: int, source: string, conflict: bool}|null
     */
    private function resolvedCandidate(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $source = array_key_first($candidates);

        return [
            'value' => $candidates[$source],
            'source' => $source,
            'conflict' => count(array_unique($candidates)) > 1,
        ];
    }
}
