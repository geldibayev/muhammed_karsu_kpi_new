<?php

use App\Http\Controllers\CriterionController;
use App\Http\Controllers\DatumController;
use App\Http\Controllers\HemisController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Auth::routes();
Route::get('/', [CriterionController::class, 'index']);
Route::get('/login/user', [HemisController::class, 'index'])->name('login.user');
Route::get('/login/d', [CriterionController::class, 'index']);

Route::prefix('home')->middleware(['auth'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::resource('/upload', DatumController::class)->only(['show', 'update']);
    Route::get('/upload-files/{id}/download', [DatumController::class, 'download'])->name('upload.file.download');
    Route::resource('/criteria', CriterionController::class)->only(['edit', 'update']);
});
