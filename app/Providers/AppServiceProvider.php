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
    }
}
