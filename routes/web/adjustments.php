<?php

use App\Http\Controllers\Dashboard\AdjustmentsDashboardController;
use App\Http\Controllers\FittingTrial\FittingTrialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Adjustments Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('adjustments', 'adjustments.', AdjustmentsDashboardController::class, 'adjustments');

/*
|--------------------------------------------------------------------------
| Fitting trials — JSON endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('adjustments')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('adjustments.')
    ->group(function () {

        Route::get('adjustments/list', [FittingTrialController::class, 'index'])
            ->name('adjustments.list');

        Route::post('adjustments', [FittingTrialController::class, 'store'])
            ->name('adjustments.store');
    });
