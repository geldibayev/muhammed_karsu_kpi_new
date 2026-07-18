<?php

namespace App\Actions;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\User;
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
    /** @param array<string, mixed> $validated */
    public function handle(User $user, Criterion $criterion, array $validated): Datum
    {
        $storedPath = null;

        try {
            $material = $this->buildMaterial($validated, $storedPath);

            $datum = DB::transaction(function () use ($user, $criterion, $validated, $material): Datum {
                $lockedCriterion = Criterion::query()->lockForUpdate()->findOrFail($criterion->id);

                Gate::forUser($user)->authorize('submit', $lockedCriterion);

                $submissionCount = Datum::query()
                    ->whereBelongsTo($user)
                    ->whereBelongsTo($lockedCriterion)
                    ->where('status', '!=', 'deleted')
                    ->count();

                if ($lockedCriterion->file_limit > 0 && $submissionCount >= $lockedCriterion->file_limit) {
                    throw ValidationException::withMessages([
                        'uploadResourceFile' => 'Resurs yuklash chegarasidan oshib ketdingiz.',
                    ]);
                }

                $datum = Datum::query()->create([
                    'user_id' => $user->id,
                    'criterion_id' => $lockedCriterion->id,
                    'year_id' => $validated['year'],
                    'language_id' => $validated['language_id'] ?? data_get($validated, 'article.lang'),
                    'material' => $material,
                    'status' => $lockedCriterion->checking === 'ai' ? 'checking' : 'received',
                    'point' => 0,
                    'reason' => $lockedCriterion->checking === 'ai'
                        ? 'AI tahlili navbatga qo\'yildi.'
                        : '',
                    'name' => $material['type'] === 'file'
                        ? $material['original_name']
                        : 'URL havola',
                ]);

                $datum->histories()->create([
                    'user_id' => $user->id,
                    'type' => 'info',
                    'message' => 'Resurs foydalanuvchi tomonidan yuborildi.',
                    'message_type' => 'submission_created',
                ]);

                return $datum;
            }, 3);
        } catch (Throwable $exception) {
            if (is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        if ($datum->status === 'checking') {
            try {
                ProcessAiDatumEvaluation::dispatch($datum->id);
            } catch (Throwable $exception) {
                Log::error('AI tekshiruv jobi navbatga qo\'yilmadi.', [
                    'datum_id' => $datum->id,
                    'exception' => $exception->getMessage(),
                ]);

                $datum->update(['reason' => 'AI tekshiruvi navbatga qo\'yilmadi. Inson tekshiruvi zarur.']);
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
        if ($validated['uploadResourceType'] === 'url') {
            $material = [
                'type' => 'url',
                'link' => $validated['uploadResourceUrl'],
            ];
        } else {
            $file = $validated['uploadResourceFile'] ?? null;

            if (! $file instanceof UploadedFile) {
                throw new RuntimeException('Tasdiqlangan yuklama fayli topilmadi.');
            }

            $storedPath = $file->store('uploads/kpi_resources/'.now()->format('Y/m'), 'local');

            if ($storedPath === false) {
                throw new RuntimeException('Yuklangan faylni saqlab bo\'lmadi.');
            }

            $material = [
                'type' => 'file',
                'disk' => 'local',
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'extension' => mb_strtolower($file->getClientOriginalExtension()),
                'mime' => $file->getMimeType(),
            ];
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
}
