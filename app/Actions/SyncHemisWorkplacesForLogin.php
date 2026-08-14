<?php

namespace App\Actions;

use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class SyncHemisWorkplacesForLogin
{
    public function __construct(
        private SyncHemisWorkplaces $syncHemisWorkplaces,
        private RecalculateReportPoints $recalculateReportPoints,
        private AssignDisciplinaryCriterionScore $assignDisciplinaryCriterionScore,
    ) {}

    public function handle(User $user, bool $allowConfiguredReviewerWithoutWorkplace = false): User
    {
        try {
            $result = $this->syncHemisWorkplaces->handle($user);
        } catch (RequestException|UnexpectedValueException $exception) {
            $isForbidden = $exception instanceof RequestException && $exception->response->forbidden();
            $isMissingWorkplace = $exception instanceof UnexpectedValueException
                && $exception->getMessage() === SyncHemisWorkplaces::MISSING_WORKPLACE_MESSAGE;
            $canUseStoredWorkplace = $isForbidden && $user->workplaces()->exists();
            $canBypassAsReviewer = ($isForbidden || $isMissingWorkplace) && $this->isConfiguredReviewer($user);

            if (! $allowConfiguredReviewerWithoutWorkplace
                || ! $user->isActive()
                || (! $canUseStoredWorkplace && ! $canBypassAsReviewer)) {
                throw $exception;
            }

            Log::warning('HEMIS workplace sync skipped during OAuth login.', [
                'user_id' => $user->getKey(),
                'hemis_id' => $user->hemis_id,
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
                'fallback' => $canUseStoredWorkplace ? 'stored_workplace' : 'configured_reviewer',
                'reason' => $exception instanceof RequestException
                    ? 'HEMIS workplace access forbidden.'
                    : $exception->getMessage(),
            ]);

            return $user;
        }

        if ($result->degreeChanged) {
            Report::query()
                ->where('status', '1')
                ->each(fn (Report $report) => $this->recalculateReportPoints->handle($report));
        }

        $this->assignDisciplinaryCriterionScore->handle($result->user);

        return $result->user;
    }

    private function isConfiguredReviewer(User $user): bool
    {
        $reviewerHemisIds = [
            ...array_values(config('kpi.ai_human_review_criterion_reviewers', [])),
            ...array_values(config('kpi.criterion_reviewers', [])),
        ];

        return in_array((string) $user->hemis_id, array_map('strval', $reviewerHemisIds), true);
    }
}
