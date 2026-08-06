<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\Report;
use App\Support\TranslatedEducationalLiteratureCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RequeueTranslatedEducationalLiteratureEvaluations
{
    public const HISTORY_TYPE = 'criterion_1_4_translation_scoring_recheck_queued';

    public function count(Report $report): int
    {
        return $this->candidates($report)->count();
    }

    public function candidates(Report $report): Builder
    {
        return Datum::query()
            ->select(['data.id', 'data.user_id', 'data.criterion_id'])
            ->where('data.status', 'accepted')
            ->whereHas('criterion', fn (Builder $query): Builder => $query
                ->whereBelongsTo($report)
                ->where('code', TranslatedEducationalLiteratureCriterionRule::CODE)
                ->where('checking', 'ai'))
            ->whereDoesntHave('histories', fn (Builder $query): Builder => $query
                ->where('message_type', self::HISTORY_TYPE));
    }

    public function requeue(int $datumId, Report $report): ?Datum
    {
        return DB::transaction(function () use ($datumId, $report): ?Datum {
            $datum = Datum::query()
                ->with('criterion:id,code,report_id,checking')
                ->lockForUpdate()
                ->find($datumId);

            if ($datum === null
                || $datum->status !== 'accepted'
                || $datum->criterion?->report_id !== $report->getKey()
                || $datum->criterion->code !== TranslatedEducationalLiteratureCriterionRule::CODE
                || $datum->criterion->checking !== 'ai'
                || $datum->histories()->where('message_type', self::HISTORY_TYPE)->exists()) {
                return null;
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
                    'message' => '1.4 kriteriyasidagi resurs tarjima, ISBN, nashr yili, sahifalar va mualliflar soni bo‘yicha yangi AI qoidasida qayta tekshiruvga yuborildi. '
                        .'Oldingi ball: '.number_format($oldPoint, 4, '.', '').'.',
                    'message_type' => self::HISTORY_TYPE,
                ],
                [
                    'user_id' => $datum->user_id,
                    'type' => 'info',
                    'message' => 'Resurs AI tekshiruv navbatiga qo‘yildi.',
                    'message_type' => 'ai_queued',
                ],
            ]);

            return $datum;
        }, attempts: 3);
    }
}
