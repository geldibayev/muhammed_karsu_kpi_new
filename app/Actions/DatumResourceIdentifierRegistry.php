<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\DatumResourceIdentifier;
use App\Services\DatumResourceFingerprintGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DatumResourceIdentifierRegistry
{
    public function __construct(private DatumResourceFingerprintGenerator $fingerprintGenerator) {}

    /**
     * @param  array<int, array{type: string, value_hash: string}>  $identifiers
     */
    public function register(Datum $datum, int $reportId, array $identifiers): void
    {
        if ($identifiers === []) {
            return;
        }

        $blockingIdentifiers = $this->blockingIdentifiers($identifiers);
        $duplicate = $this->findActiveDuplicate(
            $reportId,
            $datum->user_id,
            $blockingIdentifiers,
            $datum->getKey(),
        );

        if ($duplicate !== null) {
            throw $this->duplicateValidation($datum, $duplicate->datum_id);
        }

        $existingKeys = $datum->resourceIdentifiers()
            ->get(['type', 'value_hash'])
            ->mapWithKeys(fn (DatumResourceIdentifier $identifier): array => [
                $identifier->type.'|'.$identifier->value_hash => true,
            ]);
        $now = now();
        $rows = collect($identifiers)
            ->reject(fn (array $identifier): bool => $existingKeys->has(
                $identifier['type'].'|'.$identifier['value_hash'],
            ))
            ->map(fn (array $identifier): array => [
                'datum_id' => $datum->getKey(),
                'report_id' => $reportId,
                'user_id' => $datum->user_id,
                'type' => $identifier['type'],
                'value_hash' => $identifier['value_hash'],
                'active_value_hash' => $this->fingerprintGenerator->isBlocking($identifier['type'])
                    ? $identifier['value_hash']
                    : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if ($rows === []) {
            if (in_array($datum->status, Datum::statusesCountingTowardsUploadLimit(), true)) {
                $this->activate($datum);
            }

            return;
        }

        try {
            DatumResourceIdentifier::query()->insert($rows);
        } catch (QueryException $exception) {
            if (! $this->isActiveUniqueViolation($exception)) {
                throw $exception;
            }

            $duplicate = $this->findActiveDuplicate(
                $reportId,
                $datum->user_id,
                $blockingIdentifiers,
                $datum->getKey(),
            );

            throw $this->duplicateValidation($datum, $duplicate?->datum_id);
        }
    }

    public function activate(Datum $datum): void
    {
        $identifiers = $datum->resourceIdentifiers()
            ->whereIn('type', DatumResourceFingerprintGenerator::BLOCKING_TYPES)
            ->get(['id', 'report_id', 'type', 'value_hash']);

        if ($identifiers->isEmpty()) {
            return;
        }

        $identifierValues = $identifiers
            ->map(fn (DatumResourceIdentifier $identifier): array => [
                'type' => $identifier->type,
                'value_hash' => $identifier->value_hash,
            ])
            ->all();
        $duplicate = $this->findActiveDuplicate(
            (int) $identifiers->first()->report_id,
            $datum->user_id,
            $identifierValues,
            $datum->getKey(),
        );

        if ($duplicate !== null) {
            throw $this->duplicateValidation($datum, $duplicate->datum_id);
        }

        try {
            $datum->resourceIdentifiers()
                ->whereIn('type', DatumResourceFingerprintGenerator::BLOCKING_TYPES)
                ->update(['active_value_hash' => DB::raw('value_hash')]);
        } catch (QueryException $exception) {
            if (! $this->isActiveUniqueViolation($exception)) {
                throw $exception;
            }

            throw $this->duplicateValidation($datum);
        }
    }

    /**
     * @param  array<int, array{type: string, value_hash: string}>  $identifiers
     */
    public function storeInactive(Datum $datum, int $reportId, array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            DatumResourceIdentifier::query()->firstOrCreate([
                'datum_id' => $datum->getKey(),
                'type' => $identifier['type'],
                'value_hash' => $identifier['value_hash'],
            ], [
                'report_id' => $reportId,
                'user_id' => $datum->user_id,
                'active_value_hash' => null,
            ]);
        }
    }

    /**
     * @param  array<int, array{type: string, value_hash: string}>  $identifiers
     * @return array<int, array{type: string, value_hash: string}>
     */
    private function blockingIdentifiers(array $identifiers): array
    {
        return collect($identifiers)
            ->filter(fn (array $identifier): bool => $this->fingerprintGenerator->isBlocking($identifier['type']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{type: string, value_hash: string}>  $identifiers
     */
    private function findActiveDuplicate(
        int $reportId,
        int $userId,
        array $identifiers,
        ?int $exceptDatumId = null,
    ): ?DatumResourceIdentifier {
        if ($identifiers === []) {
            return null;
        }

        return DatumResourceIdentifier::query()
            ->where('report_id', $reportId)
            ->where('user_id', $userId)
            ->whereNotNull('active_value_hash')
            ->when(
                $exceptDatumId !== null,
                fn (Builder $query): Builder => $query->where('datum_id', '!=', $exceptDatumId),
            )
            ->where(function (Builder $query) use ($identifiers): void {
                foreach ($identifiers as $identifier) {
                    $query->orWhere(function (Builder $query) use ($identifier): void {
                        $query->where('type', $identifier['type'])
                            ->where('active_value_hash', $identifier['value_hash']);
                    });
                }
            })
            ->first(['id', 'datum_id']);
    }

    private function duplicateValidation(Datum $datum, ?int $duplicateDatumId = null): ValidationException
    {
        $input = match (data_get($datum->material, 'type')) {
            'url' => 'uploadResourceUrl',
            'h_index' => 'h_index',
            default => 'uploadResourceFile',
        };
        $reference = $duplicateDatumId !== null ? " (#{$duplicateDatumId})" : '';

        return ValidationException::withMessages([
            $input => "Ushbu resurs siz tomonidan shu hisobot davrida oldin yuklangan{$reference}.",
        ]);
    }

    private function isActiveUniqueViolation(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            && str_contains($exception->getMessage(), 'datum_identifiers_active_unique');
    }
}
