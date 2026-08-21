<?php

namespace App\Providers;

use App\Actions\DescribeAiFailure;
use App\Actions\IsGeminiCreditDepleted;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\AiHumanReviewAssignment;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\User;
use App\View\Composers\AiStatusMenuComposer;
use App\View\Composers\AuthenticatedUserSummaryComposer;
use App\View\Composers\ResourceUploadDeadlineComposer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFour();
        Queue::looping(function (Looping $event): void {
            $queues = array_map('trim', explode(',', $event->queue));

            if (! in_array(ProcessAiDatumEvaluation::QUEUE, $queues, true)) {
                return;
            }

            if (Cache::add('kpi:ai-worker:heartbeat-throttle', true, now()->addSeconds(15))) {
                Cache::put(
                    'kpi:ai-worker:heartbeat-at',
                    now()->toIso8601String(),
                    now()->addDays(30),
                );
            }

            if (Cache::add('kpi:ai-queue:recovery-throttle', true, now()->addMinute())) {
                try {
                    Artisan::call('kpi:ai:queue-pending', [
                        '--recover-stale' => true,
                        '--limit' => 100,
                        '--no-interaction' => true,
                    ]);
                } catch (Throwable $exception) {
                    Log::error('AI navbatini worker orqali tiklashda xato yuz berdi.', [
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        });
        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            if ($event->job->resolveName() !== ProcessAiDatumEvaluation::class) {
                return;
            }

            try {
                $reason = app(DescribeAiFailure::class)->handle($event->exception);

                Cache::putMany([
                    'kpi:ai-worker:last-failure-at' => now()->toIso8601String(),
                    'kpi:ai-worker:last-failure-reason' => $reason,
                    'kpi:ai-worker:last-failure-attempt' => $event->job->attempts(),
                ], now()->addDays(30));

                if (app(IsGeminiCreditDepleted::class)->handle($event->exception)) {
                    $queueName = (string) $event->job->getQueue();

                    Queue::pause($event->connectionName, $queueName);
                    Cache::putMany([
                        'kpi:ai-worker:paused-at' => now()->toIso8601String(),
                        'kpi:ai-worker:paused-reason' => $reason,
                    ], now()->addDays(30));

                    Log::critical('Gemini krediti tugagani uchun AI queue pauza qilindi.', [
                        'connection' => $event->connectionName,
                        'queue' => $queueName,
                    ]);
                }
            } catch (Throwable $monitoringException) {
                // Queue exception handling must not be interrupted by monitoring.
                Log::error('AI queue xatosi monitoringida nosozlik yuz berdi.', [
                    'exception' => $monitoringException,
                ]);
            }
        });
        Event::listen(JobTimedOut::class, function (JobTimedOut $event): void {
            if ($event->job->resolveName() !== ProcessAiDatumEvaluation::class) {
                return;
            }

            try {
                Cache::putMany([
                    'kpi:ai-worker:last-failure-at' => now()->toIso8601String(),
                    'kpi:ai-worker:last-failure-reason' => 'AI tekshiruvi 60 soniyalik job limitidan oshdi.',
                    'kpi:ai-worker:last-failure-attempt' => $event->job->attempts(),
                ], now()->addDays(30));
            } catch (Throwable) {
                // Queue timeout handling must not be interrupted by monitoring.
            }
        });
        Gate::define(
            'view-ai-status',
            fn (User $user): bool => $user->isSuperAdmin()
                || (string) $user->hemis_id === (string) config('kpi.ai_status_viewer_hemis_id'),
        );
        Gate::define(
            'view-ai-human-reviewer-statistics',
            fn (User $user): bool => $user->isSuperAdmin(),
        );
        Gate::define(
            'manage-ai-operations',
            fn (User $user): bool => $user->isSuperAdmin()
                || (string) $user->hemis_id === (string) config('kpi.ai_operations_manager_hemis_id'),
        );
        Gate::define(
            'view-resource-statistics',
            fn (User $user): bool => Gate::forUser($user)->allows('view-ai-status')
                || in_array(
                    (string) $user->hemis_id,
                    array_map('strval', config('kpi.resource_statistics_viewer_hemis_ids', [])),
                    true,
                ),
        );
        Gate::define(
            'view-ratings',
            fn (User $user): bool => array_intersect(
                $user->rol ?? [],
                ['super_admin', 'moder', 'dean', 'department', 'teacher', 'user'],
            ) !== [],
        );
        Gate::define('rebuild-report-points', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('manage-upload-permissions', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('export-employment-data', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define(
            'manage-kpi-settings',
            fn (User $user): bool => $user->isSuperAdmin()
                || (string) $user->hemis_id === (string) config('kpi.settings_manager_hemis_id'),
        );
        Gate::define(
            'access-manual-reviews',
            fn (User $user): bool => $user->isSuperAdmin()
                || CriterionReviewerAssignment::query()
                    ->where('hemis_id', $user->hemis_id)
                    ->whereHas(
                        'criterion',
                        fn (Builder $query): Builder => $query->where('checking', '!=', 'ai'),
                    )
                    ->exists(),
        );
        Gate::define(
            'access-ai-human-reviews',
            fn (User $user): bool => $user->isSuperAdmin()
                || AiHumanReviewAssignment::criterionCodesFor((int) $user->hemis_id) !== []
                || AiHumanReviewAssignment::query()
                    ->active()
                    ->where('hemis_id', $user->hemis_id)
                    ->exists()
                || Datum::query()
                    ->where('reviewer_hemis_id', $user->hemis_id)
                    ->where('status', 'checking')
                    ->whereHas(
                        'criterion',
                        fn (Builder $query): Builder => $query->where('checking', 'ai'),
                    )
                    ->exists(),
        );
        View::composer('layouts.app', AuthenticatedUserSummaryComposer::class);
        View::composer('layouts.app', AiStatusMenuComposer::class);
        View::composer(['layouts.app', 'auth.login'], ResourceUploadDeadlineComposer::class);
    }
}
