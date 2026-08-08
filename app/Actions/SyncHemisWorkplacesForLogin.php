<?php

namespace App\Actions;

use App\Models\Report;
use App\Models\User;

class SyncHemisWorkplacesForLogin
{
    public function __construct(
        private SyncHemisWorkplaces $syncHemisWorkplaces,
        private RecalculateReportPoints $recalculateReportPoints,
        private AssignDisciplinaryCriterionScore $assignDisciplinaryCriterionScore,
    ) {}

    public function handle(User $user): User
    {
        $result = $this->syncHemisWorkplaces->handle($user);

        if ($result->degreeChanged) {
            Report::query()
                ->where('status', '1')
                ->each(fn (Report $report) => $this->recalculateReportPoints->handle($report));
        }

        $this->assignDisciplinaryCriterionScore->handle($result->user);

        return $result->user;
    }
}
