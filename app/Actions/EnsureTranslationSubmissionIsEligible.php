<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\User;
use App\Services\DatumResourceFingerprintGenerator;
use App\Support\TranslatedEducationalLiteratureCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class EnsureTranslationSubmissionIsEligible
{
    public function __construct(private DatumResourceFingerprintGenerator $fingerprintGenerator) {}

    /**
     * @param  array<int, array{type: string, value_hash: string}>  $identifiers
     */
    public function handle(User $user, Criterion $criterion, array $identifiers): void
    {
        $duplicate = $this->findPreviouslyScoredDuplicate(
            $user->getKey(),
            $criterion,
            $identifiers,
        );

        if ($duplicate === null) {
            return;
        }

        $criterionCode = $duplicate->criterion?->code ?? '1.2/1.3';

        throw ValidationException::withMessages([
            'uploadResourceFile' => "Ushbu fayl {$criterionCode} mezonida #{$duplicate->getKey()} resurs sifatida qabul qilingan va ball olgan. 1.4 mezoniga faqat boshqa tildan qilingan tarjimani tasdiqlovchi alohida resurs yuklang.",
        ]);
    }

    /**
     * @param  array<int, array{type: string, value_hash: string}>  $identifiers
     */
    public function findPreviouslyScoredDuplicate(
        int $userId,
        Criterion $criterion,
        array $identifiers,
    ): ?Datum {
        if (! TranslatedEducationalLiteratureCriterionRule::supports($criterion->code)) {
            return null;
        }

        $blockingIdentifiers = collect($identifiers)
            ->filter(fn (array $identifier): bool => $this->fingerprintGenerator->isBlocking($identifier['type']))
            ->mapWithKeys(fn (array $identifier): array => [
                $identifier['type'].'|'.$identifier['value_hash'] => true,
            ]);

        if ($blockingIdentifiers->isEmpty()) {
            return null;
        }

        return Datum::query()
            ->select(['id', 'user_id', 'criterion_id', 'year_id', 'material', 'status', 'point'])
            ->with('criterion:id,code,report_id')
            ->where('user_id', $userId)
            ->where('status', 'accepted')
            ->where('point', '>', 0)
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->where('report_id', $criterion->report_id)
                ->whereIn('code', TranslatedEducationalLiteratureCriterionRule::PREVIOUSLY_SCORED_CODES))
            ->orderBy('id')
            ->get()
            ->first(function (Datum $datum) use ($blockingIdentifiers): bool {
                return collect($this->fingerprintGenerator->forDatum($datum))
                    ->contains(fn (array $identifier): bool => $blockingIdentifiers->has(
                        $identifier['type'].'|'.$identifier['value_hash'],
                    ));
            });
    }
}
