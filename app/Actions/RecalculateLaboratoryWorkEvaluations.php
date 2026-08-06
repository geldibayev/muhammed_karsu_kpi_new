<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Formula;
use App\Models\Report;
use App\Support\LaboratoryWorkCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecalculateLaboratoryWorkEvaluations
{
    public const RECALCULATED_HISTORY_TYPE = 'criterion_1_8_legacy_point_recalculated';

    public const RECHECK_HISTORY_TYPE = 'criterion_1_8_author_recheck_queued';

    /** @return Builder<Datum> */
    public function candidates(Report $report): Builder
    {
        return Datum::query()
            ->select([
                'data.id',
                'data.user_id',
                'data.criterion_id',
                'data.material',
                'data.author_count',
                'data.point',
                'data.reason',
            ])
            ->where('data.status', 'accepted')
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->whereBelongsTo($report)
                ->where('code', LaboratoryWorkCriterionRule::CODE)
                ->where('checking', 'ai'));
    }

    /** @return array{total: int, recalculations: int, rechecks: int, unchanged: int} */
    public function analyse(Report $report, ?int $limit = null): array
    {
        $this->criterion($report);
        $counts = ['total' => 0, 'recalculations' => 0, 'rechecks' => 0, 'unchanged' => 0];

        foreach ($this->candidates($report)->with($this->analysisRelations())->lazyById(200) as $datum) {
            $resolution = $this->resolveAuthorCount($datum);
            $outcome = $resolution === null || $resolution['conflict']
                ? 'rechecks'
                : (abs($datum->point - LaboratoryWorkCriterionRule::pointForAuthorCount($resolution['value'])) >= 0.00005
                    || $datum->author_count !== $resolution['value'] ? 'recalculations' : 'unchanged');

            $counts['total']++;
            $counts[$outcome]++;

            if ($limit !== null && $limit <= $counts['recalculations'] + $counts['rechecks']) {
                break;
            }
        }

        return $counts;
    }

    /** @return array{datum: Datum, outcome: 'recalculated'|'recheck'}|null */
    public function process(int $datumId, Report $report): ?array
    {
        return DB::transaction(function () use ($datumId, $report): ?array {
            $datum = Datum::query()
                ->with(array_merge(['criterion:id,code,report_id,checking'], $this->analysisRelations()))
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'accepted'
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->code !== LaboratoryWorkCriterionRule::CODE
                || $datum->criterion->checking !== 'ai') {
                return null;
            }

            $resolution = $this->resolveAuthorCount($datum);

            if ($resolution !== null && ! $resolution['conflict']) {
                $point = LaboratoryWorkCriterionRule::pointForAuthorCount($resolution['value']);

                if ($datum->author_count === $resolution['value'] && abs($datum->point - $point) < 0.00005) {
                    return null;
                }

                $oldPoint = $datum->point;
                $datum->update([
                    'author_count' => $resolution['value'],
                    'point' => $point,
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '1.8 balli eski ma\'lumotdagi aniq mualliflar soni bo\'yicha qayta hisoblandi. '
                        .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'. Hisob: 0.5 / '
                        .$resolution['value'].' = '.number_format($point, 4, '.', '').' ball. '
                        .'Mualliflar soni manbasi: '.$resolution['source'].'.',
                    'message_type' => self::RECALCULATED_HISTORY_TYPE,
                ]);

                return ['datum' => $datum, 'outcome' => 'recalculated'];
            }

            $oldPoint = $datum->point;
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
            $datum->histories()->createMany([
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => '1.8 resursida mualliflar soni '.($resolution === null ? 'topilmadi' : 'manbalar orasida zid chiqdi')
                        .'. Resurs AI tekshiruviga qaytarildi. Oldingi ball: '
                        .number_format($oldPoint, 4, '.', '').'.',
                    'message_type' => self::RECHECK_HISTORY_TYPE,
                ],
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => 'Resurs AI tekshiruv navbatiga qo\'yildi.',
                    'message_type' => 'ai_queued',
                ],
            ]);

            return ['datum' => $datum, 'outcome' => 'recheck'];
        }, attempts: 3);
    }

    private function criterion(Report $report): Criterion
    {
        $criterion = Criterion::query()
            ->whereBelongsTo($report)
            ->where('code', LaboratoryWorkCriterionRule::CODE)
            ->with('formula:id,code')
            ->first();

        if ($criterion === null) {
            throw new RuntimeException('Tanlangan hisobotda 1.8 kriteriyasi topilmadi.');
        }

        if (! $criterion->usesFormula(Formula::Maximum)) {
            throw new RuntimeException('1.8 kriteriyasi uchun maksimal ball formulasi sozlanmagan. Migratsiyalarni bajaring.');
        }

        return $criterion;
    }

    /** @return array<int|string, mixed> */
    private function analysisRelations(): array
    {
        return [
            'histories' => fn ($query) => $query
                ->select(['id', 'datum_id', 'message'])
                ->where('message_type', 'ai_evaluation')
                ->latest('id'),
        ];
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
            $value = $this->authorCountFromText($history->message);

            if ($value !== null) {
                $candidates['ai_history'] = $value;
                break;
            }
        }

        $reasonValue = $this->authorCountFromText($datum->reason);

        if ($reasonValue !== null) {
            $candidates['reason'] = $reasonValue;
        }

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

    private function authorCountFromText(?string $text): ?int
    {
        if (blank($text)) {
            return null;
        }

        foreach ([
            '/(?:jami\s+)?mualliflar\s+soni\s*[=:\p{Pd}]?\s*(\d{1,4})/iu',
            '/(\d{1,4})\s+(?:nafar\s+)?muallif/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                $value = (int) $matches[1];

                if ($value >= 1 && $value <= 1000) {
                    return $value;
                }
            }
        }

        return null;
    }
}
