<?php

namespace App\Console\Commands;

use App\Actions\RecalculateReportPoints;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\Report;
use App\Support\InternationalCooperationCriterionRule;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecheckInternationalCooperationAiEvaluations extends Command
{
    private const RECALCULATED_HISTORY_TYPE = 'criterion_2_1_6_server_recalculated';

    private const QUEUED_HISTORY_TYPE = 'criterion_2_1_6_percentage_recheck_queued';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:recheck-international-cooperation-ai-evaluations
                            {report : Qayta tekshiriladigan hisobot IDsi}
                            {--datum=* : Faqat ko‘rsatilgan datum IDlarini qayta tekshirish}
                            {--limit= : Qayta ishlanadigan resurslar sonini cheklash}
                            {--apply : Aniqlangan ballarni saqlash va noaniq resurslarni AI navbatiga qo‘yish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '2.1.6 mezonidagi tasdiqlangan resurslarni foizli qoida bilan qayta hisoblaydi yoki AI navbatiga qo‘yadi';

    /**
     * Execute the console command.
     */
    public function handle(RecalculateReportPoints $recalculateReportPoints): int
    {
        $reportId = filter_var($this->argument('report'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $limit = $this->validatedLimit();
        $datumIds = $this->validatedDatumIds();

        if ($reportId === false || $limit === false || $datumIds === false) {
            return self::FAILURE;
        }

        $report = Report::query()->find($reportId);

        if ($report === null) {
            $this->error("Hisobot topilmadi: {$reportId}.");

            return self::FAILURE;
        }

        $candidateCount = $this->candidateCount($report, $datumIds, $limit);
        $this->info("2.1.6 mezoni bo‘yicha qayta tekshiruvga mos resurslar: {$candidateCount}");

        if (! $this->option('apply')) {
            $this->warn('Dry-run: o‘zgarish kiritilmadi.');

            return self::SUCCESS;
        }

        $recalculated = 0;
        $queued = 0;
        $failedDispatches = 0;

        foreach ($this->candidates($report, $datumIds, $limit) as $candidate) {
            $processed = $this->processDatum((int) $candidate->getKey(), $report);

            if ($processed === null) {
                continue;
            }

            if (! $processed['queued']) {
                $recalculated++;

                continue;
            }

            $queuedDatum = $processed['datum'];

            try {
                ProcessAiDatumEvaluation::dispatch(
                    $queuedDatum->getKey(),
                    $queuedDatum->criterion_id,
                )->afterCommit();
                $queued++;
            } catch (Throwable $exception) {
                $failedDispatches++;
                report($exception);
                $queuedDatum->histories()->create([
                    'user_id' => $queuedDatum->user_id,
                    'type' => 'warning',
                    'message' => '2.1.6 resursini qayta AI navbatiga yuborishda xatolik yuz berdi.',
                    'message_type' => 'ai_failed',
                ]);
            }
        }

        if ($recalculated > 0 || $queued > 0) {
            $recalculateReportPoints->handle($report);
        }

        $this->info("2.1.6 mezoni bo‘yicha serverda qayta hisoblandi: {$recalculated}");
        $this->info("2.1.6 mezoni bo‘yicha AI qayta tekshiruviga qo‘yildi: {$queued}");

        if ($failedDispatches > 0) {
            $this->error("Navbatga qo‘yishda xato: {$failedDispatches}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param list<int> $datumIds */
    private function candidateCount(Report $report, array $datumIds, ?int $limit): int
    {
        $candidateCount = 0;

        foreach ($this->candidates($report, $datumIds, $limit) as $datum) {
            $candidateCount++;
        }

        return $candidateCount;
    }

    /**
     * @param  list<int>  $datumIds
     * @return iterable<int, Datum>
     */
    private function candidates(Report $report, array $datumIds, ?int $limit): iterable
    {
        $candidateCount = 0;

        foreach ($this->candidateQuery($report, $datumIds)->lazyById(200, column: 'data.id', alias: 'id') as $datum) {
            yield $datum;
            $candidateCount++;

            if ($limit !== null && $candidateCount >= $limit) {
                return;
            }
        }
    }

    /** @param list<int> $datumIds */
    private function candidateQuery(Report $report, array $datumIds): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id'])
            ->where('data.status', 'accepted')
            ->when($datumIds !== [], fn (Builder $query): Builder => $query->whereKey($datumIds))
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->where('report_id', $report->getKey())
                ->where('code', InternationalCooperationCriterionRule::CODE)
                ->where('checking', 'ai'))
            ->whereDoesntHave('histories', fn (Builder $query): Builder => $query
                ->whereIn('message_type', [
                    self::RECALCULATED_HISTORY_TYPE,
                    self::QUEUED_HISTORY_TYPE,
                ]));
    }

    /** @return array{datum: Datum, queued: bool}|null */
    private function processDatum(int $datumId, Report $report): ?array
    {
        return DB::transaction(function () use ($datumId, $report): ?array {
            $datum = Datum::query()
                ->with([
                    'criterion:id,report_id,code,checking',
                    'user:id,degree',
                ])
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'accepted'
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->code !== InternationalCooperationCriterionRule::CODE
                || $datum->criterion->checking !== 'ai') {
                return null;
            }

            if ($datum->histories()
                ->whereIn('message_type', [
                    self::RECALCULATED_HISTORY_TYPE,
                    self::QUEUED_HISTORY_TYPE,
                ])
                ->exists()) {
                return null;
            }

            $universityTier = $this->resolveUniversityTier($datum);
            $maximumPoint = InternationalCooperationCriterionRule::maximumPointForEvaluationCategory(
                $datum->user?->degree,
            );
            $point = $universityTier === null
                ? null
                : InternationalCooperationCriterionRule::pointForUniversityTier(
                    $maximumPoint,
                    $universityTier,
                );

            if ($universityTier === 'outside_top_1000' || $point !== null) {
                $status = $universityTier === 'outside_top_1000' ? 'cancelled' : 'accepted';
                $point ??= 0.0;
                $percentage = InternationalCooperationCriterionRule::percentageForUniversityTier(
                    $universityTier,
                );
                $calculation = $universityTier === 'outside_top_1000'
                    ? 'Top-1000 dan past — 0 ball.'
                    : $this->tierLabel($universityTier).' — '.$percentage.'%; '
                        .number_format($maximumPoint, 2, '.', '').' × '.$percentage
                        .'% = '.number_format($point, 2, '.', '').' ball.';
                $reason = trim((string) $datum->reason);
                $reason = ($reason === '' ? '' : $reason.' ')
                    .'Tizim qayta hisob-kitobi: '.$calculation;

                $datum->update([
                    'status' => $status,
                    'point' => $point,
                    'university_tier' => $universityTier,
                    'reason' => $reason,
                    'reviewer_hemis_id' => null,
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => $status === 'accepted' ? 'success' : 'error',
                    'message' => $reason,
                    'message_type' => self::RECALCULATED_HISTORY_TYPE,
                ]);

                return ['datum' => $datum, 'queued' => false];
            }

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
                    'message' => '2.1.6 resursining Top darajasi mavjud ma’lumotlardan aniqlanmadi va AI qayta tekshiruviga belgilandi.',
                    'message_type' => self::QUEUED_HISTORY_TYPE,
                ],
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => 'Resurs AI tekshiruv navbatiga qo‘yildi.',
                    'message_type' => 'ai_queued',
                ],
            ]);

            return ['datum' => $datum, 'queued' => true];
        }, 3);
    }

    private function resolveUniversityTier(Datum $datum): ?string
    {
        $history = $datum->histories()
            ->latest('id')
            ->limit(20)
            ->pluck('message')
            ->implode(' ');
        $material = json_encode($datum->material, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $evidence = mb_strtolower(implode(' ', [
            $datum->name,
            (string) $datum->reason,
            $history,
            is_string($material) ? $material : '',
        ]));

        if (preg_match('/(?:xorij(?:lik|iy)\s+talabalarni\s+jalb\s+qil(?:gan|ingan)|foreign\s+students?\s+(?:were\s+)?attracted|привлеч\pL*\s+иностранн\pL*\s+студент)/iu', $evidence) === 1) {
            return 'foreign_students';
        }

        if (preg_match('/(?:al[\s\pP_]*farabi|аль[\s\pP_]*фараби|әл[\s\pP_]*фараби)/iu', $evidence) === 1) {
            return 'top_300';
        }

        foreach ([
            $datum->university_tier,
            data_get($datum->material, 'university_tier'),
            data_get($datum->material, 'data.university_tier'),
            data_get($datum->material, 'article.university_tier'),
        ] as $storedTier) {
            if (is_string($storedTier)
                && array_key_exists(
                    $storedTier,
                    InternationalCooperationCriterionRule::UNIVERSITY_TIER_POINTS,
                )) {
                return $storedTier;
            }
        }

        preg_match_all(
            '/(\d{1,4})\s*[-–—]?\s*(?:o[‘’\'ʻ]?rin(?:da|i)?|place|rank|мест\pL*)/iu',
            $evidence,
            $rankMatches,
        );
        $rankTiers = collect($rankMatches[1] ?? [])
            ->map(fn (string $rank): ?string => InternationalCooperationCriterionRule::tierForRank((int) $rank))
            ->filter()
            ->unique()
            ->values();

        if ($rankTiers->count() === 1) {
            return $rankTiers->first();
        }

        if (preg_match('/(?:top\s*[-–—]?\s*1000\s*dan\s*past|outside\s+(?:the\s+)?top\s*[-–—]?\s*1000|ниже\s+топ\s*[-–—]?\s*1000)/iu', $evidence) === 1) {
            return 'outside_top_1000';
        }

        $mentionedTiers = collect([
            'top_100' => '/top\s*[-–—]?\s*(?:1\s*[-–—]\s*)?100(?!0)/iu',
            'top_300' => '/top\s*[-–—]?\s*(?:101\s*[-–—]\s*)?300/iu',
            'top_500' => '/top\s*[-–—]?\s*(?:301\s*[-–—]\s*)?500/iu',
            'top_1000' => '/top\s*[-–—]?\s*(?:501\s*[-–—]\s*)?1000/iu',
        ])->filter(fn (string $pattern): bool => preg_match($pattern, $evidence) === 1);

        return $mentionedTiers->count() === 1 ? $mentionedTiers->keys()->first() : null;
    }

    private function tierLabel(string $universityTier): string
    {
        return match ($universityTier) {
            'top_100' => 'Top-1–100',
            'top_300' => 'Top-101–300',
            'top_500' => 'Top-301–500',
            'top_1000' => 'Top-501–1000',
            'foreign_students' => 'Xorijlik talabalarni jalb qilish',
        };
    }

    private function validatedLimit(): int|false|null
    {
        $value = $this->option('limit');

        if ($value === null) {
            return null;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($limit === false) {
            $this->error('--limit musbat butun son bo‘lishi kerak.');
        }

        return $limit;
    }

    /** @return list<int>|false */
    private function validatedDatumIds(): array|false
    {
        $ids = [];

        foreach ((array) $this->option('datum') as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($id === false) {
                $this->error('--datum qiymatlari musbat butun son bo‘lishi kerak.');

                return false;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }
}
