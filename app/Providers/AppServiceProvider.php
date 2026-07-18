<?php

namespace App\Providers;

use App\Models\CriterionReviewerAssignment;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('rebuild-report-points', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('manage-reviewer-assignments', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define(
            'access-manual-reviews',
            fn (User $user): bool => $user->isSuperAdmin()
                || CriterionReviewerAssignment::query()->where('hemis_id', $user->hemis_id)->exists(),
        );
    }
}
