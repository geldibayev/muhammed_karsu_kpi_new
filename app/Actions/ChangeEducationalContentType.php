<?php

namespace App\Actions;

use App\Models\CriterionEvaluation;
use App\Models\CriterionManualScoreOption;
use App\Models\Datum;
use App\Models\User;
use App\Support\EducationalContentCriterionRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeEducationalContentType
{
    public function __construct(private RecalculateReportPoints $recalculateReportPoints) {}

    public function handle(User $reviewer, Datum $datum, int $scoreOptionId): Datum
    {
        $updatedDatum = DB::transaction(function () use ($reviewer, $datum, $scoreOptionId): Datum {
            $lockedDatum = Datum::query()
                ->with(['criterion.report', 'manualScoreOption', 'user'])
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('changeEducationalContentType', $lockedDatum);

            User::query()
                ->whereKey($lockedDatum->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $option = CriterionManualScoreOption::query()
                ->whereKey($scoreOptionId)
                ->where('criterion_id', $lockedDatum->criterion_id)
                ->where('active', true)
                ->lockForUpdate()
                ->first();
            $percentage = $option === null
                ? null
                : EducationalContentCriterionRule::percentageFor($option->code);

            if ($percentage === null) {
                throw ValidationException::withMessages([
                    'score_option_id' => 'Tanlangan resurs turi 1.1 mezoni uchun qo‘llab-quvvatlanmaydi.',
                ]);
            }

            $alreadyUsed = Datum::query()
                ->where('user_id', $lockedDatum->user_id)
                ->where('criterion_id', $lockedDatum->criterion_id)
                ->where('status', 'accepted')
                ->where('manual_score_option_id', $option->getKey())
                ->where('id', '!=', $lockedDatum->getKey())
                ->exists();

            if ($alreadyUsed) {
                throw ValidationException::withMessages([
                    'score_option_id' => 'Bu foydalanuvchining 1.1 mezonida ushbu resurs turi allaqachon tasdiqlangan.',
                ]);
            }

            $evaluation = CriterionEvaluation::query()
                ->where('criterion_id', $lockedDatum->criterion_id)
                ->where('evaluation', $lockedDatum->user->degree)
                ->where('has', '1')
                ->first();
            $point = $evaluation === null
                ? null
                : EducationalContentCriterionRule::pointFor((float) $evaluation->score, $option->code);

            if ($point === null) {
                throw ValidationException::withMessages([
                    'score_option_id' => 'Foydalanuvchi toifasi uchun maksimal ball sozlanmagan.',
                ]);
            }

            $oldLabel = $lockedDatum->manualScoreOption === null
                ? 'ko‘rsatilmagan tur'
                : (string) data_get(
                    $lockedDatum->manualScoreOption->label,
                    'uz',
                    $lockedDatum->manualScoreOption->code,
                );
            $newLabel = (string) data_get($option->label, 'uz', $option->code);
            $message = "1.1 resurs turi {$oldLabel}dan {$newLabel}ga o‘zgartirildi. "
                .'Ball avtomatik ravishda '.number_format($point, 2, '.', '').' etib qayta hisoblandi.';

            $lockedDatum->update([
                'manual_score_option_id' => $option->getKey(),
                'point' => $point,
            ]);
            $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'info',
                'message' => $message,
                'message_type' => 'criterion_1_1_resource_type_changed',
            ]);

            return $lockedDatum;
        }, 3);

        $this->recalculateReportPoints->handle($updatedDatum->criterion->report);

        return $updatedDatum->refresh();
    }
}
