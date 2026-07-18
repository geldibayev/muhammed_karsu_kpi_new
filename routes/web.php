<?php

use App\Http\Controllers\CriterionController;
use App\Http\Controllers\CriterionPointController;
use App\Http\Controllers\DatumController;
use App\Http\Controllers\DatumHistoryController;
use App\Http\Controllers\HemisController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManualReviewController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReviewerAssignmentController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Auth::routes();
Route::get('/', [CriterionController::class, 'index']);
Route::get('/login/user', [HemisController::class, 'index'])->name('login.user');
Route::get('/login/d', [CriterionController::class, 'index']);

Route::prefix('home')->middleware(['auth'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::post('/logout', [HomeController::class, 'logout'])->name('auth.logout');
    Route::get('/profile', [HomeController::class, 'profile'])->name('profile');
    Route::get('/ratings', [RatingController::class, 'index'])
        ->middleware('can:view-ratings')
        ->name('ratings.index');
    Route::get('/users/roles', [UserRoleController::class, 'index'])->name('users.roles.index');
    Route::put('/users/{user}/roles', [UserRoleController::class, 'update'])->name('users.roles.update');
    Route::get('/reviewer-assignments', [ReviewerAssignmentController::class, 'index'])
        ->name('reviewer-assignments.index');
    Route::get('/reviews', [ManualReviewController::class, 'index'])
        ->middleware('can:access-manual-reviews')
        ->name('reviews.index');
    Route::get('/reviews/{datum}', [ManualReviewController::class, 'show'])->name('reviews.show');
    Route::patch('/reviews/{datum}/approve', [ManualReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('/reviews/{datum}/reject', [ManualReviewController::class, 'reject'])->name('reviews.reject');
    Route::post('/reports/{report}/points/rebuild', [CriterionPointController::class, 'rebuild'])->middleware('can:rebuild-report-points')->name('reports.points.rebuild');
    Route::get('/upload/{upload}', [DatumController::class, 'show'])->name('upload.show');
    Route::post('/upload/{upload}', [DatumController::class, 'store'])->name('upload.store');
    Route::get('/submissions/{datum}', [DatumController::class, 'details'])->name('upload.details');
    Route::delete('/submissions/{datum}', [DatumController::class, 'destroy'])->name('upload.destroy');
    Route::get('/files/{status}', [DatumHistoryController::class, 'index'])->name('files.show');
    Route::get('/submissions/{datum}/download', [DatumController::class, 'download'])->name('upload.file.download');
    Route::resource('/criteria', CriterionController::class)->only(['edit', 'update']);
});
