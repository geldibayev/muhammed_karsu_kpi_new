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

    public function handle(User $user, bool $allowLoginFallback = false): User
    {
        try {
            $result = $this->syncHemisWorkplaces->handle($user);
        } catch (RequestException|UnexpectedValueException $exception) {
            $isForbidden = $exception instanceof RequestException && $exception->response->forbidden();
            $isMissingWorkplace = $exception instanceof UnexpectedValueException
                && $exception->getMessage() === SyncHemisWorkplaces::MISSING_WORKPLACE_MESSAGE;
            $hasStoredWorkplace = $isForbidden && $user->workplaces()->exists();
            $isConfiguredReviewer = $this->isConfiguredReviewer($user);

            if (! $allowLoginFallback
                || ! $user->isActive()
                || (! $isForbidden && (! $isMissingWorkplace || ! $isConfiguredReviewer))) {
                throw $exception;
            }

            Log::warning('HEMIS workplace sync skipped during OAuth login.', [
                'user_id' => $user->getKey(),
                'hemis_id' => $user->hemis_id,
                'status' => $exception instanceof RequestException ? $exception->response->status() : null,
                'degraded_mode' => $isForbidden ? 'oauth_only_403' : null,
                'fallback' => $hasStoredWorkplace
                    ? 'stored_workplace'
                    : ($isConfiguredReviewer ? 'configured_reviewer' : 'oauth_only'),
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
