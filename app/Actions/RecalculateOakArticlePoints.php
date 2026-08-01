<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Formula;
use App\Models\Report;
use App\Services\OakArticleScoreCalculator;
use App\Support\OakArticleCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecalculateOakArticlePoints
{
    public function __construct(
        private OakArticleScoreCalculator $oakArticleScoreCalculator,
        private RecalculateReportPoints $recalculateReportPoints,
    ) {}

    /**
     * @return array{total: int, changes: int, unmatched_ids: array<int, int>, conflicts: int, sources: array<string, int>}
     */
    public function handle(Report $report, bool $apply = false): array
    {
        if (! $apply) {
            $analysis = $this->analyse($report);
            unset($analysis['rows']);

            return $analysis;
        }

        $result = DB::transaction(function () use ($report): array {
            Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $analysis = $this->analyse($report, true);

            if ($analysis['unmatched_ids'] !== []) {
                throw new RuntimeException(
                    'Mualliflar soni aniqlanmagan resurslar mavjud: '.implode(', ', $analysis['unmatched_ids']),
                );
            }

            foreach ($analysis['rows'] as $row) {
                if (! $row['changes']) {
                    continue;
                }

                /** @var Datum $datum */
                $datum = $row['datum'];
                $datum->update([
                    'author_count' => $row['author_count'],
                    'point' => $row['point'],
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '3.1.1 balli yangi qoida bo‘yicha qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($row['old_point'], 4, '.', '').'. '
                        .'Bazaviy ball: '.number_format($row['base_point'], 2, '.', '').'. '
                        .'Mualliflar soni: '.$row['author_count'].'. '
                        .'Yangi ball: '.number_format($row['point'], 4, '.', '').'. '
                        .'Mualliflar soni manbasi: '.$row['source'].'.',
                    'message_type' => 'oak_article_rule_recalculated',
                ]);
            }

            unset($analysis['rows']);

            return $analysis;
        }, 3);

        $this->recalculateReportPoints->handle($report);

        return $result;
    }

    /**
     * @return array{total: int, changes: int, unmatched_ids: array<int, int>, conflicts: int, sources: array<string, int>, rows: Collection<int, array{datum: Datum, author_count: int, point: float, old_point: float, base_point: float, source: string, changes: bool}>}
     */
    private function analyse(Report $report, bool $lockForUpdate = false): array
    {
        $criterion = $this->criterion($report, $lockForUpdate);
        $data = Datum::query()
            ->where('criterion_id', $criterion->getKey())
            ->where('status', 'accepted')
            ->with([
                'user:id,degree',
                'histories' => fn ($query) => $query
                    ->select(['id', 'datum_id', 'message'])
                    ->where('message_type', 'ai_evaluation')
                    ->latest('id'),
            ])
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->orderBy('id')
            ->get();

        $unmatchedIds = [];
        $conflicts = 0;
        $sources = [];
        $rows = collect();

        foreach ($data as $datum) {
            $resolved = $this->resolveAuthorCount($datum);

            if ($resolved === null || $datum->user === null) {
                $unmatchedIds[] = $datum->getKey();

                continue;
            }

            $conflicts += $resolved['conflict'] ? 1 : 0;
            $sources[$resolved['source']] = ($sources[$resolved['source']] ?? 0) + 1;
            $basePoint = $this->oakArticleScoreCalculator->basePoint($datum->user->degree);
            $point = $this->oakArticleScoreCalculator->calculate(
                $datum->user->degree,
                $resolved['author_count'],
            );

            $rows->push([
                'datum' => $datum,
                'author_count' => $resolved['author_count'],
                'point' => $point,
                'old_point' => $datum->point,
                'base_point' => $basePoint,
                'source' => $resolved['source'],
                'changes' => $datum->author_count !== $resolved['author_count']
                    || abs($datum->point - $point) >= 0.00005,
            ]);
        }

        return [
            'total' => $data->count(),
            'changes' => $rows->where('changes', true)->count(),
            'unmatched_ids' => $unmatchedIds,
            'conflicts' => $conflicts,
            'sources' => $sources,
            'rows' => $rows,
        ];
    }

    private function criterion(Report $report, bool $lockForUpdate): Criterion
    {
        $criterion = Criterion::query()
            ->whereBelongsTo($report)
            ->where('code', OakArticleCriterionRule::CODE)
            ->with('formula:id,code')
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->first();

        if ($criterion === null) {
            throw new RuntimeException('Tanlangan hisobotda 3.1.1 kriteriyasi topilmadi.');
        }

        if (! $criterion->usesFormula(Formula::Maximum)) {
            throw new RuntimeException('3.1.1 kriteriyasi uchun maksimal ball formulasi sozlanmagan. Migratsiyalarni bajaring.');
        }

        return $criterion;
    }

    /** @return array{author_count: int, source: string, conflict: bool}|null */
    private function resolveAuthorCount(Datum $datum): ?array
    {
        $candidates = [];

        if ($datum->author_count !== null && $datum->author_count >= 1) {
            $candidates['structured'] = $datum->author_count;
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

        $materialAuthorCount = data_get($datum->material, 'article.authors_num');

        if (is_numeric($materialAuthorCount)
            && (int) $materialAuthorCount >= 1
            && (int) $materialAuthorCount <= 1000) {
            $candidates['material'] = (int) $materialAuthorCount;
        }

        if ($candidates === []) {
            return null;
        }

        $source = array_key_first($candidates);

        return [
            'author_count' => $candidates[$source],
            'source' => $source,
            'conflict' => count(array_unique($candidates)) > 1,
        ];
    }

    private function authorCountFromText(?string $text): ?int
    {
        if (blank($text)) {
            return null;
        }

        $patterns = [
            '/mualliflar soni\s*[:=]\s*(\d+)/iu',
            '/umumiy mualliflar soni\s*[:=]\s*(\d+)/iu',
            '/mualliflar soni\s*[-–—]\s*(\d+)/iu',
            '/mualliflar soni\s+(\d+)\s+nafar/iu',
            '/maqolada(?:\s+jami)?\s+(\d+)\s+nafar muallif/iu',
            '/(\d+)\s+nafar muallif/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches) < 1) {
                continue;
            }

            $authorCount = (int) end($matches[1]);

            if ($authorCount >= 1 && $authorCount <= 1000) {
                return $authorCount;
            }
        }

        return null;
    }
}
