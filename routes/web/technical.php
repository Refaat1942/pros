<?php

use App\Http\Controllers\Dashboard\TechnicalDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:technical
*/

Route::prefix('technical')->name('technical.')->group(function () {
    // ->middleware('auth:technical')
    Route::get('/', [TechnicalDashboardController::class, 'index'])->name('dashboard');
});
