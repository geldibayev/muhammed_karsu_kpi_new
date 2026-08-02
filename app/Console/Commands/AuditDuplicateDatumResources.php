<?php

namespace App\Console\Commands;

use App\Actions\DatumResourceIdentifierRegistry;
use App\Actions\RecalculateReportPoints;
use App\Models\Datum;
use App\Models\Report;
use App\Services\DatumResourceFingerprintGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditDuplicateDatumResources extends Command
{
    protected $signature = 'kpi:duplicates:audit
        {--report= : Faqat ko‘rsatilgan hisobot ID sini tekshirish}
        {--apply : Aniq dublikatlarni audit bilan bekor qilish va ballarni qayta hisoblash}';

    protected $description = 'Bir foydalanuvchining bitta hisobotdagi takroriy resurslarini aniqlaydi va ixtiyoriy tuzatadi';

    /** @var array<int, int> */
    private array $parents = [];

    public function handle(
        DatumResourceFingerprintGenerator $fingerprintGenerator,
        DatumResourceIdentifierRegistry $identifierRegistry,
        RecalculateReportPoints $recalculateReportPoints,
    ): int {
        $reportId = $this->reportId();
        if ($reportId === false) {
            return self::FAILURE;
        }

        $data = Datum::query()
            ->select([
                'id',
                'user_id',
                'criterion_id',
                'year_id',
                'material',
                'status',
                'point',
                'duplicate_of_id',
            ])
            ->with('criterion:id,report_id')
            ->whereIn('status', Datum::statusesCountingTowardsUploadLimit())
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->when($reportId !== null, fn (Builder $query): Builder => $query->where('report_id', $reportId)))
            ->orderBy('id')
            ->get()
            ->filter(fn (Datum $datum): bool => $datum->criterion !== null)
            ->values();

        if ($data->isEmpty()) {
            $this->info('Tekshirish uchun faol resurs topilmadi.');

            return self::SUCCESS;
        }

        [$identifiersByDatum, $blockingGroups, $candidateGroups] = $this->groupIdentifiers(
            $data,
            $fingerprintGenerator,
        );
        $this->buildExactClusters($data, $blockingGroups);
        [$duplicateMap, $duplicateRows, $affectedReportIds] = $this->duplicatePlan($data);
        $manualCandidateRows = $this->manualCandidateRows($candidateGroups, $data);

        $this->table(
            ['Dublikat', 'Asosiy', 'Foydalanuvchi', 'Hisobot', 'Holat', 'Ball'],
            array_slice($duplicateRows, 0, 100),
        );

        if (count($duplicateRows) > 100) {
            $this->warn('Jadvalda dastlabki 100 ta dublikat ko‘rsatildi.');
        }

        if ($manualCandidateRows !== []) {
            $this->newLine();
            $this->warn('Quyidagi guruhlar faqat nom/jurnal mosligi sabab moderator tekshiruvini talab qiladi:');
            $this->table(
                ['Foydalanuvchi', 'Hisobot', 'Resurs ID lari'],
                array_slice($manualCandidateRows, 0, 100),
            );
        }

        $this->newLine();
        $this->line('Tekshirilgan faol resurslar: '.$data->count());
        $this->line('Aniq dublikat klasterlari: '.collect($duplicateMap)->values()->unique()->count());
        $this->line('Ortiqcha resurslar: '.count($duplicateMap));
        $this->line('Olib tashlanadigan resurs ballari yig‘indisi: '.number_format(
            collect($duplicateRows)->sum(fn (array $row): float => (float) $row[5]),
            4,
            '.',
            '',
        ));
        $this->line('Faqat nom/jurnal bo‘yicha moderator ko‘rishi kerak bo‘lgan guruhlar: '.count($manualCandidateRows));

        if (! $this->option('apply')) {
            $this->warn('Dry-run yakunlandi. Bazaga o‘zgartirish kiritilmadi. Tuzatish uchun --apply ishlating.');

            return self::SUCCESS;
        }

        $this->applyDuplicates($data, $duplicateMap, $identifiersByDatum, $identifierRegistry);
        $this->registerCanonicalIdentifiers($data, $duplicateMap, $identifiersByDatum, $identifierRegistry);

        foreach ($affectedReportIds as $affectedReportId) {
            $report = Report::query()->find($affectedReportId);

            if ($report !== null) {
                $recalculateReportPoints->handle($report);
            }
        }

        $this->info('Aniq dublikatlar audit tarixi bilan bekor qilindi va tegishli hisobot ballari qayta hisoblandi.');

        return self::SUCCESS;
    }

    private function reportId(): int|false|null
    {
        $option = $this->option('report');
        if ($option === null || $option === '') {
            return null;
        }

        if (! ctype_digit((string) $option) || ! Report::query()->whereKey((int) $option)->exists()) {
            $this->error('Ko‘rsatilgan hisobot topilmadi.');

            return false;
        }

        return (int) $option;
    }

    /**
     * @param  Collection<int, Datum>  $data
     * @return array{array<int, array<int, array{type: string, value_hash: string}>>, array<string, array<int, int>>, array<string, array<int, int>>}
     */
    private function groupIdentifiers(
        Collection $data,
        DatumResourceFingerprintGenerator $fingerprintGenerator,
    ): array {
        $identifiersByDatum = [];
        $blockingGroups = [];
        $candidateGroups = [];

        foreach ($data as $datum) {
            $reportId = $datum->criterion->report_id;
            $identifiers = $fingerprintGenerator->forDatum($datum);
            $identifiersByDatum[$datum->getKey()] = $identifiers;

            foreach ($identifiers as $identifier) {
                $key = implode('|', [
                    $reportId,
                    $datum->user_id,
                    $identifier['type'],
                    $identifier['value_hash'],
                ]);

                if ($fingerprintGenerator->isBlocking($identifier['type'])) {
                    $blockingGroups[$key][] = $datum->getKey();
                } else {
                    $candidateGroups[$key][] = $datum->getKey();
                }
            }
        }

        return [$identifiersByDatum, $blockingGroups, $candidateGroups];
    }

    /**
     * @param  Collection<int, Datum>  $data
     * @param  array<string, array<int, int>>  $blockingGroups
     */
    private function buildExactClusters(Collection $data, array $blockingGroups): void
    {
        $this->parents = $data->mapWithKeys(fn (Datum $datum): array => [
            $datum->getKey() => $datum->getKey(),
        ])->all();

        foreach ($blockingGroups as $datumIds) {
            $datumIds = array_values(array_unique($datumIds));
            $first = array_shift($datumIds);

            if ($first === null) {
                continue;
            }

            foreach ($datumIds as $datumId) {
                $this->union($first, $datumId);
            }
        }
    }

    /**
     * @param  Collection<int, Datum>  $data
     * @return array{array<int, int>, array<int, array<int, int|float|string>>, array<int, int>}
     */
    private function duplicatePlan(Collection $data): array
    {
        $duplicateMap = [];
        $duplicateRows = [];
        $affectedReportIds = [];
        $clusters = $data->groupBy(fn (Datum $datum): int => $this->find($datum->getKey()));

        foreach ($clusters as $cluster) {
            if ($cluster->count() < 2) {
                continue;
            }

            $canonical = $cluster->sort(fn (Datum $left, Datum $right): int => $this->compareCanonical(
                $left,
                $right,
            ))->first();

            foreach ($cluster as $datum) {
                if ($datum->is($canonical)) {
                    continue;
                }

                $duplicateMap[$datum->getKey()] = $canonical->getKey();
                $reportId = $datum->criterion->report_id;
                $affectedReportIds[$reportId] = $reportId;
                $duplicateRows[] = [
                    $datum->getKey(),
                    $canonical->getKey(),
                    $datum->user_id,
                    $reportId,
                    $datum->status,
                    (float) $datum->point,
                ];
            }
        }

        return [$duplicateMap, $duplicateRows, array_values($affectedReportIds)];
    }

    /**
     * @param  array<string, array<int, int>>  $candidateGroups
     * @param  Collection<int, Datum>  $data
     * @return array<int, array{int, int, string}>
     */
    private function manualCandidateRows(array $candidateGroups, Collection $data): array
    {
        $dataById = $data->keyBy('id');

        return collect($candidateGroups)
            ->map(function (array $datumIds) use ($dataById): ?array {
                $roots = collect($datumIds)->map(fn (int $datumId): int => $this->find($datumId));
                if ($roots->unique()->count() < 2) {
                    return null;
                }

                $datumIds = array_values(array_unique($datumIds));
                $first = $dataById->get($datumIds[0]);
                if (! $first instanceof Datum) {
                    return null;
                }

                return [
                    $first->user_id,
                    (int) $first->criterion->report_id,
                    implode(', ', $datumIds),
                ];
            })
            ->filter()
            ->unique(fn (array $row): string => implode('|', $row))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Datum>  $data
     * @param  array<int, int>  $duplicateMap
     * @param  array<int, array<int, array{type: string, value_hash: string}>>  $identifiersByDatum
     */
    private function applyDuplicates(
        Collection $data,
        array $duplicateMap,
        array $identifiersByDatum,
        DatumResourceIdentifierRegistry $identifierRegistry,
    ): void {
        $dataById = $data->keyBy('id');

        foreach ($duplicateMap as $duplicateId => $canonicalId) {
            $datum = $dataById->get($duplicateId);
            if (! $datum instanceof Datum) {
                continue;
            }

            DB::transaction(function () use (
                $datum,
                $canonicalId,
                $identifiersByDatum,
                $identifierRegistry,
            ): void {
                $lockedDatum = Datum::query()->lockForUpdate()->find($datum->getKey());
                if ($lockedDatum === null
                    || ! in_array($lockedDatum->status, Datum::statusesCountingTowardsUploadLimit(), true)) {
                    return;
                }

                $reportId = (int) $datum->criterion->report_id;
                $message = "Tizim dublikat auditida resurs #{$canonicalId} bilan bir xil deb topildi. ";
                $message .= 'Takroriy ball hisobdan chiqarildi, fayl audit uchun saqlandi.';
                $lockedDatum->update([
                    'status' => 'deleted',
                    'point' => 0,
                    'duplicate_of_id' => $canonicalId,
                    'reviewer_hemis_id' => null,
                    'reason' => $message,
                ]);
                $lockedDatum->histories()->create([
                    'user_id' => $lockedDatum->user_id,
                    'type' => 'warning',
                    'message' => $message,
                    'message_type' => 'duplicate_resource_invalidated',
                ]);
                $identifierRegistry->storeInactive(
                    $lockedDatum,
                    $reportId,
                    $identifiersByDatum[$lockedDatum->getKey()] ?? [],
                );
            }, 3);
        }
    }

    /**
     * @param  Collection<int, Datum>  $data
     * @param  array<int, int>  $duplicateMap
     * @param  array<int, array<int, array{type: string, value_hash: string}>>  $identifiersByDatum
     */
    private function registerCanonicalIdentifiers(
        Collection $data,
        array $duplicateMap,
        array $identifiersByDatum,
        DatumResourceIdentifierRegistry $identifierRegistry,
    ): void {
        foreach ($data as $datum) {
            if (isset($duplicateMap[$datum->getKey()])) {
                continue;
            }

            DB::transaction(function () use ($datum, $identifiersByDatum, $identifierRegistry): void {
                $lockedDatum = Datum::query()->lockForUpdate()->find($datum->getKey());
                if ($lockedDatum === null
                    || ! in_array($lockedDatum->status, Datum::statusesCountingTowardsUploadLimit(), true)) {
                    return;
                }

                $identifierRegistry->register(
                    $lockedDatum,
                    (int) $datum->criterion->report_id,
                    $identifiersByDatum[$datum->getKey()] ?? [],
                );
            }, 3);
        }
    }

    private function compareCanonical(Datum $left, Datum $right): int
    {
        $pointComparison = (float) $right->point <=> (float) $left->point;

        if ($pointComparison !== 0) {
            return $pointComparison;
        }

        return $left->getKey() <=> $right->getKey();
    }

    private function find(int $datumId): int
    {
        $parent = $this->parents[$datumId] ?? $datumId;
        if ($parent === $datumId) {
            return $datumId;
        }

        return $this->parents[$datumId] = $this->find($parent);
    }

    private function union(int $left, int $right): void
    {
        $leftRoot = $this->find($left);
        $rightRoot = $this->find($right);

        if ($leftRoot !== $rightRoot) {
            $this->parents[$rightRoot] = $leftRoot;
        }
    }
}
