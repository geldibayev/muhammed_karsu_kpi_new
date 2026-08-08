<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\User;
use App\Services\HIndexScoreCalculator;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class CorrectHIndexProfileValue
{
    public const PROFILES = [
        'scopus' => 'Scopus',
        'web_of_science' => 'Web of Science',
        'research_gate' => 'ResearchGate',
    ];

    public function __construct(
        private HIndexScoreCalculator $scoreCalculator,
        private RecalculateReportPoints $recalculateReportPoints,
    ) {}

    /**
     * @return array{changed: bool, datum_id: int, old_value: int, new_value: int, old_point: float, new_point: float, report_id: int}
     */
    public function handle(
        int $hemisId,
        string $profile,
        int $expectedValue,
        int $newValue,
        ?User $actor,
        bool $apply,
        ?int $datumId = null,
    ): array {
        if (! array_key_exists($profile, self::PROFILES)) {
            throw new UnexpectedValueException('Qo‘llab-quvvatlanmaydigan H-index profili.');
        }

        $user = User::query()->where('hemis_id', $hemisId)->first();
        if ($user === null) {
            throw new UnexpectedValueException("HEMIS ID {$hemisId} foydalanuvchi topilmadi.");
        }

        $datum = $this->resolveDatum($user, $profile, $expectedValue, $newValue, $datumId);
        $currentValue = (int) data_get($datum->material, "profiles.{$profile}.value");
        $previewPoint = $this->calculatedPoint($datum, $profile, $newValue);
        $result = [
            'changed' => $currentValue !== $newValue,
            'datum_id' => (int) $datum->getKey(),
            'old_value' => $currentValue,
            'new_value' => $newValue,
            'old_point' => (float) $datum->point,
            'new_point' => $previewPoint,
            'report_id' => (int) $datum->criterion->report_id,
        ];

        if (! $apply || ! $result['changed']) {
            return $result;
        }

        if ($actor === null) {
            throw new UnexpectedValueException('O‘zgarishni audit qilish uchun administrator ko‘rsatilishi shart.');
        }

        DB::transaction(function () use (
            $datum,
            $profile,
            $expectedValue,
            $newValue,
            $actor,
            $previewPoint,
        ): void {
            $lockedDatum = Datum::query()
                ->with(['criterion.report', 'criterion.criterionEvaluations', 'user'])
                ->lockForUpdate()
                ->findOrFail($datum->getKey());
            $lockedValue = (int) data_get($lockedDatum->material, "profiles.{$profile}.value");

            if ($lockedValue === $newValue) {
                return;
            }

            if ($lockedValue !== $expectedValue) {
                throw new UnexpectedValueException(
                    "Datum #{$lockedDatum->getKey()} dagi joriy qiymat {$lockedValue}; kutilgan qiymat {$expectedValue} emas.",
                );
            }

            $material = $lockedDatum->material;
            data_set($material, "profiles.{$profile}.value", $newValue);
            $message = self::PROFILES[$profile]." H-index ma’lumoti administrator tomonidan {$expectedValue} dan "
                ."{$newValue} ga tuzatildi. Oldingi ball: "
                .number_format((float) $lockedDatum->point, 2, '.', '').'. Qayta hisoblangan ball: '
                .number_format($previewPoint, 2, '.', '').'.';

            $lockedDatum->update(['material' => $material]);
            $lockedDatum->histories()->create([
                'user_id' => $actor->getKey(),
                'type' => 'warning',
                'message' => $message,
                'message_type' => 'h_index_profile_corrected',
            ]);

            $this->recalculateReportPoints->handle($lockedDatum->criterion->report);
        }, attempts: 3);

        return $result;
    }

    private function resolveDatum(
        User $user,
        string $profile,
        int $expectedValue,
        int $newValue,
        ?int $datumId,
    ): Datum {
        $data = Datum::query()
            ->whereBelongsTo($user)
            ->where('status', 'accepted')
            ->whereHas('criterion', fn ($query) => $query->where('code', Criterion::H_INDEX_CODE))
            ->when($datumId !== null, fn ($query) => $query->whereKey($datumId))
            ->with(['criterion.report', 'criterion.criterionEvaluations', 'user'])
            ->orderBy('id')
            ->get()
            ->filter(function (Datum $datum) use ($profile, $expectedValue, $newValue): bool {
                $value = data_get($datum->material, "profiles.{$profile}.value");

                return is_numeric($value) && in_array((int) $value, [$expectedValue, $newValue], true);
            })
            ->values();

        if ($data->isEmpty()) {
            throw new UnexpectedValueException('Mos accepted H-index resursi topilmadi.');
        }

        if ($data->count() > 1) {
            throw new UnexpectedValueException(
                'Bir nechta mos resurs topildi: '.$data->pluck('id')->implode(', ').'. --datum parametrini kiriting.',
            );
        }

        return $data->first();
    }

    private function calculatedPoint(Datum $datum, string $profile, int $newValue): float
    {
        $maximumShare = $datum->criterion->criterionEvaluations
            ->firstWhere('evaluation', $datum->user?->degree)?->score;
        if (! is_numeric($maximumShare)) {
            throw new UnexpectedValueException('Foydalanuvchi toifasi uchun H-index balli sozlanmagan.');
        }

        $profiles = $datum->hIndexProfiles();
        data_set($profiles, "{$profile}.value", $newValue);

        return $this->scoreCalculator->calculate($profiles, max(0, (float) $maximumShare))['total'];
    }
}
