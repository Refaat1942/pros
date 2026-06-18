<?php

use App\Http\Controllers\Dashboard\AdjustmentsDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:adjustments
*/

Route::prefix('adjustments')->name('adjustments.')->group(function () {
    // ->middleware('auth:adjustments')
    Route::get('/', [AdjustmentsDashboardController::class, 'index'])->name('dashboard');
});
