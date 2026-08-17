<?php

namespace App\Actions;

use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConfirmDatumFinalReview
{
    public function handle(User $reviewer, Datum $datum): DatumHistory
    {
        return DB::transaction(function () use ($reviewer, $datum): DatumHistory {
            $lockedDatum = Datum::query()
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($reviewer)->authorize('confirmFinalReview', $lockedDatum);

            $reviewerName = $reviewer->full ?: ($reviewer->short ?: 'Noma’lum mas’ul');

            return $lockedDatum->histories()->create([
                'user_id' => $reviewer->getKey(),
                'type' => 'success',
                'message' => 'Resurs yakuniy tekshiruvdan o‘tkazildi. Mas’ul: '.$reviewerName.'.',
                'message_type' => DatumHistory::FINAL_REVIEW_CONFIRMED,
            ]);
        }, 3);
    }
}
