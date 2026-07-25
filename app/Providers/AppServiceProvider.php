<?php

namespace App\Providers;

use App\Actions\DescribeAiFailure;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\CriterionReviewerAssignment;
use App\Models\User;
use App\View\Composers\AiStatusMenuComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for(
            'gemini-api',
            fn (): Limit => Limit::perMinute(
                max(1, (int) config('kpi.ai_requests_per_minute', 10)),
            )->by('gemini-api'),
        );
        Queue::before(function (JobProcessing $event): void {
            if ($event->job->resolveName() === ProcessAiDatumEvaluation::class) {
                Cache::put('kpi:ai-worker:last-seen-at', now()->toIso8601String(), now()->addDays(30));
            }
        });
        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            if ($event->job->resolveName() !== ProcessAiDatumEvaluation::class) {
                return;
            }

            try {
                Cache::putMany([
                    'kpi:ai-worker:last-failure-at' => now()->toIso8601String(),
                    'kpi:ai-worker:last-failure-reason' => app(DescribeAiFailure::class)
                        ->handle($event->exception),
                    'kpi:ai-worker:last-failure-attempt' => $event->job->attempts(),
                ], now()->addDays(30));
            } catch (Throwable) {
                // Queue exception handling must not be interrupted by monitoring.
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
            fn (User $user): bool => (string) $user->hemis_id
                === (string) config('kpi.ai_status_viewer_hemis_id'),
        );
        Gate::define(
            'view-resource-statistics',
            fn (User $user): bool => Gate::forUser($user)->allows('view-ai-status'),
        );
        Gate::define(
            'view-ratings',
            fn (User $user): bool => array_intersect(
                $user->rol ?? [],
                ['super_admin', 'moder', 'dean', 'department', 'teacher', 'user'],
            ) !== [],
        );
        Gate::define('rebuild-report-points', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define(
            'access-manual-reviews',
            fn (User $user): bool => CriterionReviewerAssignment::query()
                ->where('hemis_id', $user->hemis_id)
                ->exists(),
        );
        View::composer('layouts.app', AiStatusMenuComposer::class);
    }
}
