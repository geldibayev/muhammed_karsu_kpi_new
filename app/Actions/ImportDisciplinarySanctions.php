<?php

namespace App\Actions;

use App\Models\DisciplinarySanction;
use App\Models\DisciplinarySanctionImport;
use App\Models\Report;
use App\Models\User;
use App\Support\XlsxFirstSheetReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportDisciplinarySanctions
{
    private const MAX_FILE_BYTES = 10_000_000;

    public function __construct(
        private XlsxFirstSheetReader $reader,
        private AssignDisciplinaryCriterionScore $assignScore,
        private RecalculateReportPoints $recalculateReportPoints,
    ) {}

    /** @return array{rows: int, existing_users: int, scored_users: int, changed_scores: int, changed_snapshot: bool} */
    public function handle(string $filename, bool $apply): array
    {
        $path = $this->validatedPath($filename);
        $sanctions = $this->sanctions($this->reader->read($path));
        $hash = hash_file('sha256', $path);

        if (! is_string($hash)) {
            throw new RuntimeException('XLSX fayl hashini hisoblab bo‘lmadi.');
        }

        $existingUsers = User::query()->whereIn('hemis_id', array_keys($sanctions))->count();
        $result = [
            'rows' => count($sanctions),
            'existing_users' => $existingUsers,
            'scored_users' => 0,
            'changed_scores' => 0,
            'changed_snapshot' => DisciplinarySanctionImport::query()
                ->latest('id')
                ->value('source_hash') !== $hash,
        ];

        if (! $apply) {
            return $result;
        }

        return DB::transaction(function () use ($filename, $hash, $sanctions, $result): array {
            $latestImport = DisciplinarySanctionImport::query()->latest('id')->first();
            $import = $latestImport?->source_hash === $hash
                ? $latestImport
                : DisciplinarySanctionImport::query()->create([
                    'source_file' => $filename,
                    'source_hash' => $hash,
                    'row_count' => count($sanctions),
                    'imported_at' => now(),
                ]);

            if ($latestImport?->source_hash !== $hash) {
                DisciplinarySanction::query()->delete();
                $now = now();
                collect($sanctions)
                    ->map(fn (int $row, string $hemisId): array => [
                        'hemis_id' => $hemisId,
                        'import_id' => $import->getKey(),
                        'source_row' => $row,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->chunk(500)
                    ->each(fn ($rows) => DisciplinarySanction::query()->insert($rows->all()));
            }

            $reportIds = [];
            User::query()->active()->orderBy('id')->chunkById(200, function ($users) use (&$result, &$reportIds): void {
                foreach ($users as $user) {
                    $score = $this->assignScore->handle($user, recalculate: false);
                    $result['scored_users']++;
                    $result['changed_scores'] += $score['changed'];
                    $reportIds = [...$reportIds, ...$score['report_ids']];
                }
            });

            Report::query()
                ->whereKey(array_values(array_unique($reportIds)))
                ->each(fn (Report $report): mixed => $this->recalculateReportPoints->handle($report));

            return $result;
        }, attempts: 3);
    }

    private function validatedPath(string $filename): string
    {
        if ($filename !== basename($filename) || mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('Faqat private storage ichidagi .xlsx fayl nomini kiriting.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($filename)) {
            throw new RuntimeException("Private storage ichida {$filename} topilmadi.");
        }

        $size = $disk->size($filename);
        if ($size <= 0 || $size > self::MAX_FILE_BYTES) {
            throw new RuntimeException('XLSX fayl hajmi yaroqsiz yoki 10 MB chegaradan katta.');
        }

        $mimeType = $disk->mimeType($filename);
        if (! in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ], true)) {
            throw new RuntimeException('Fayl MIME turi XLSX formatiga mos emas.');
        }

        return $disk->path($filename);
    }

    /** @param  array<int, array<string, string>>  $rows
     * @return array<string, int>
     */
    private function sanctions(array $rows): array
    {
        if ($rows === []) {
            throw new RuntimeException('XLSX faylida qatorlar topilmadi.');
        }

        $headerRowNumber = array_key_first($rows);
        $header = $rows[$headerRowNumber];
        $hemisColumn = collect($header)->search(function (string $value): bool {
            $normalized = mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? ''));

            return $normalized === 'hemis id';
        });

        if (! is_string($hemisColumn)) {
            throw new RuntimeException('XLSX sarlavhasida "hemis id" ustuni topilmadi.');
        }

        $sanctions = [];
        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === $headerRowNumber) {
                continue;
            }

            $hemisId = trim($row[$hemisColumn] ?? '');
            if ($hemisId === '' && count(array_filter($row, 'filled')) === 0) {
                continue;
            }

            if (preg_match('/^\d{10}$/', $hemisId) !== 1) {
                throw new RuntimeException("{$rowNumber}-qatordagi HEMIS ID yaroqsiz.");
            }

            if (array_key_exists($hemisId, $sanctions)) {
                throw new RuntimeException("HEMIS ID {$hemisId} ro‘yxatda takrorlangan.");
            }

            $sanctions[$hemisId] = $rowNumber;
        }

        if ($sanctions === []) {
            throw new RuntimeException('XLSX faylida birorta yaroqli HEMIS ID topilmadi.');
        }

        return $sanctions;
    }
}
