<?php

namespace App\Actions;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Datum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RequeueCancelledAiDatum
{
    public function __construct(private DatumResourceIdentifierRegistry $identifierRegistry) {}

    public function handle(User $administrator, Datum $datum): Datum
    {
        return DB::transaction(function () use ($administrator, $datum): Datum {
            $lockedDatum = Datum::query()
                ->with('criterion:id,checking')
                ->lockForUpdate()
                ->findOrFail($datum->getKey());

            Gate::forUser($administrator)->authorize('requeueAiEvaluation', $lockedDatum);
            $this->identifierRegistry->activate($lockedDatum);

            $lockedDatum->update([
                'status' => 'checking',
                'point' => 0,
                'author_count' => null,
                'page_count' => null,
                'reason' => Datum::PUBLIC_CHECKING_REASON,
                'reviewer_hemis_id' => null,
            ]);
            $lockedDatum->histories()->createMany([
                [
                    'user_id' => $administrator->getKey(),
                    'type' => 'info',
                    'message' => 'Super administrator resursni AI qayta tekshiruviga yubordi.',
                    'message_type' => 'ai_manual_recheck_queued',
                ],
                [
                    'user_id' => $administrator->getKey(),
                    'type' => 'info',
                    'message' => 'Resurs AI tekshiruv navbatiga qo‘yildi.',
                    'message_type' => 'ai_queued',
                ],
            ]);

            ProcessAiDatumEvaluation::dispatch(
                $lockedDatum->getKey(),
                $lockedDatum->criterion_id,
            )->afterCommit();

            return $lockedDatum;
        }, 3);
    }
}
