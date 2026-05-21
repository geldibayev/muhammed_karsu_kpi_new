<?php

use App\Http\Controllers\CriterionController;
use App\Http\Controllers\DatumController;
use App\Http\Controllers\HemisController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Auth::routes();
Route::get('/', [CriterionController::class, 'index']);
Route::get('/login/user', [HemisController::class, 'index'])->name('login.user');

Route::prefix('home')->middleware(['auth'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::resource('/upload', DatumController::class)->only(['show', 'update']);
});
