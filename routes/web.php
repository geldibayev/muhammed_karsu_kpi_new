<?php

use App\Http\Controllers\CriterionController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CriterionController::class, 'index']);
Auth::routes();
Route::get('/home', [HomeController::class, 'index'])->name('home');
