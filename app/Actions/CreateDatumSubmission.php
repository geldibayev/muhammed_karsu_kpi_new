<?php

namespace App\Actions;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\AiHumanReviewAssignment;
use App\Models\Criterion;
use App\Models\CriterionUploadPermission;
use App\Models\Datum;
use App\Models\Option;
use App\Models\User;
use App\Services\DatumResourceFingerprintGenerator;
use App\Support\FixedPerResourceHumanReviewCriterionRule;
use App\Support\ResourceUploadWindow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CreateDatumSubmission
{
    public function __construct(
        private DatumResourceFingerprintGenerator $fingerprintGenerator,
        private DatumResourceIdentifierRegistry $identifierRegistry,
        private EnsureTranslationSubmissionIsEligible $ensureTranslationSubmissionIsEligible,
        private ResourceUploadWindow $resourceUploadWindow,
    ) {}

    /** @param array<string, mixed> $validated */
    public function handle(User $user, Criterion $criterion, array $validated): Datum
    {
        $storedPath = null;

        try {
            $material = $this->buildMaterial($validated, $storedPath);
            $identifiers = $this->fingerprintGenerator->forMaterial(
                $material,
                (int) $validated['year'],
            );

            $datum = DB::transaction(function () use (
                $user,
                $criterion,
                $validated,
                $material,
                $identifiers,
            ): Datum {
                $lockedCriterion = Criterion::query()->lockForUpdate()->findOrFail($criterion->id);
                $replacementDatum = filled($validated['replacement_datum_id'] ?? null)
                    ? Datum::query()->lockForUpdate()->findOrFail((int) $validated['replacement_datum_id'])
                    : null;
                $uploadPermission = $replacementDatum === null
                    && (! Option::resourceUploadsEnabled() || ! $this->resourceUploadWindow->isOpen())
                    ? CriterionUploadPermission::query()
                        ->available()
                        ->whereBelongsTo($user)
                        ->whereBelongsTo($lockedCriterion)
                        ->lockForUpdate()
                        ->first()
                    : null;

                if ($replacementDatum !== null) {
                    abort_unless($replacementDatum->criterion_id === $lockedCriterion->id, 403);
                    Gate::forUser($user)->authorize('replaceFourOneOneReference', $replacementDatum);
                } else {
                    Gate::forUser($user)->authorize('submit', $lockedCriterion);
                }

                $submissionCount = Datum::query()
                    ->whereBelongsTo($user)
                    ->whereBelongsTo($lockedCriterion)
                    ->countsTowardsUploadLimit()
                    ->count();

                if ($lockedCriterion->file_limit > 0 && $submissionCount >= $lockedCriterion->file_limit) {
                    throw ValidationException::withMessages([
                        'uploadResourceFile' => 'Resurs yuklash chegarasidan oshib ketdingiz.',
                    ]);
                }

                $this->ensureTranslationSubmissionIsEligible->handle(
                    $user,
                    $lockedCriterion,
                    $identifiers,
                );

                $isDirectHumanReview = $lockedCriterion->checking === 'ai'
                    && $lockedCriterion->code === FixedPerResourceHumanReviewCriterionRule::FOUR_ONE_ONE_CODE;
                $reviewerHemisId = $isDirectHumanReview
                    ? AiHumanReviewAssignment::reviewerHemisIdFor($lockedCriterion, sharedLock: true)
                    : null;

                if ($isDirectHumanReview && $reviewerHemisId === null) {
                    throw new RuntimeException('4.1.1 kriteriyasi uchun mas’ul sozlanmagan.');
                }

                $datum = Datum::query()->create([
                    'user_id' => $user->id,
                    'criterion_id' => $lockedCriterion->id,
                    'year_id' => $validated['year'],
                    'language_id' => $validated['language_id'] ?? data_get($validated, 'article.lang'),
                    'material' => $material,
                    'status' => $lockedCriterion->checking === 'ai' ? 'checking' : 'received',
                    'reviewer_hemis_id' => $reviewerHemisId,
                    'point' => 0,
                    'reason' => $isDirectHumanReview
                        ? Datum::PUBLIC_CHECKING_REASON
                        : ($lockedCriterion->checking === 'ai' ? 'AI tahlili navbatga qo\'yildi.' : ''),
                    'name' => match ($material['type']) {
                        'file' => $material['original_name'],
                        'h_index' => 'H-index profillari',
                        default => 'URL havola',
                    },
                ]);
                $this->identifierRegistry->register(
                    $datum,
                    $lockedCriterion->report_id,
                    $identifiers,
                );

                if ($uploadPermission !== null) {
                    $uploadPermission->update([
                        'active_key' => null,
                        'used_at' => now(),
                        'datum_id' => $datum->getKey(),
                    ]);
                    $datum->histories()->create([
                        'user_id' => $user->getKey(),
                        'type' => 'info',
                        'message' => "Bir martalik maxsus yuklash ruxsati #{$uploadPermission->getKey()} ishlatildi.",
                        'message_type' => 'criterion_upload_permission_used',
                    ]);
                }

                $datum->histories()->create([
                    'user_id' => $user->id,
                    'type' => 'info',
                    'message' => 'Resurs foydalanuvchi tomonidan yuborildi.',
                    'message_type' => 'submission_created',
                ]);

                if ($isDirectHumanReview) {
                    $datum->histories()->create([
                        'user_id' => $user->id,
                        'type' => 'info',
                        'message' => "Resurs HEMIS ID {$reviewerHemisId} mas’ulga qo‘lda tekshirish uchun biriktirildi.",
                        'message_type' => 'ai_human_review_assigned',
                    ]);
                }

                $replacementDatum?->histories()->create([
                    'user_id' => $user->id,
                    'type' => 'info',
                    'message' => "Ma’lumotnoma o‘rniga yangi resurs #{$datum->id} yuborildi.",
                    'message_type' => 'four_one_one_reference_replacement_submitted',
                ]);

                return $datum;
            }, 3);
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        if ($datum->status === 'checking' && $datum->reviewer_hemis_id === null) {
            try {
                ProcessAiDatumEvaluation::dispatch($datum->id, $datum->criterion_id);
            } catch (Throwable $exception) {
                Log::error('AI tekshiruv jobi navbatga qo\'yilmadi.', [
                    'datum_id' => $datum->id,
                    'exception' => $exception->getMessage(),
                ]);

                $reason = 'AI tekshiruvi navbatga qo‘yilmadi. Queue ulanishi yoki worker sozlamasi tekshirilishi kerak.';

                DB::transaction(function () use ($datum, $reason): void {
                    $lockedDatum = Datum::query()->lockForUpdate()->find($datum->id);

                    if ($lockedDatum === null || $lockedDatum->status !== 'checking') {
                        return;
                    }

                    $lockedDatum->update(['reason' => $reason]);
                    $lockedDatum->histories()->create([
                        'user_id' => $lockedDatum->user_id,
                        'type' => 'warning',
                        'message' => $reason,
                        'message_type' => 'ai_failed',
                    ]);
                }, 3);
            }
        }

        return $datum;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildMaterial(array $validated, ?string &$storedPath): array
    {
        if ($validated['uploadResourceType'] === 'h_index') {
            return [
                'type' => 'h_index',
                'profiles' => $validated['h_index'],
            ];
        }

        if ($validated['uploadResourceType'] === 'url') {
            $material = [
                'type' => 'url',
                'link' => $validated['uploadResourceUrl'],
            ];
        } else {
            $file = $validated['uploadResourceFile'] ?? null;
            $doi = data_get($validated, 'article.doi');

            if (! $file instanceof UploadedFile) {
                if (! is_string($doi) || blank($doi)) {
                    throw new RuntimeException('Tasdiqlangan yuklama fayli yoki DOI topilmadi.');
                }

                $material = [
                    'type' => 'url',
                    'link' => $this->doiUrl($doi),
                ];
            } else {
                $storedPath = $file->store('uploads/kpi_resources/'.now()->format('Y/m'), 'local');

                if ($storedPath === false) {
                    throw new RuntimeException('Yuklangan faylni saqlab bo\'lmadi.');
                }

                $sha256 = hash_file('sha256', $file->getRealPath());
                if (! is_string($sha256)) {
                    throw new RuntimeException('Yuklangan fayl uchun nazorat summasi hisoblanmadi.');
                }

                $material = [
                    'type' => 'file',
                    'disk' => 'local',
                    'path' => $storedPath,
                    'original_name' => $file->getClientOriginalName(),
                    'extension' => mb_strtolower($file->getClientOriginalExtension()),
                    'mime' => $file->getMimeType(),
                    'sha256' => $sha256,
                ];
            }
        }

        foreach (['article', 'data'] as $metadataKey) {
            if (isset($validated[$metadataKey]) && is_array($validated[$metadataKey])) {
                $material[$metadataKey] = Arr::where(
                    $validated[$metadataKey],
                    static fn (mixed $value): bool => $value !== null && $value !== '',
                );
            }
        }

        return $material;
    }

    private function doiUrl(string $doi): string
    {
        $doi = preg_replace(
            '~^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)~iu',
            '',
            trim($doi),
        ) ?? trim($doi);

        return 'https://doi.org/'.$doi;
    }
}
