<?php

use App\Http\Controllers\Dashboard\OperationsDashboardController;
use App\Http\Controllers\Manufacturing\ManufacturingStageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operations Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('operations', 'operations.', OperationsDashboardController::class, 'operations');

/*
|--------------------------------------------------------------------------
| Manufacturing sub-stages — JSON endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('operations')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('operations.')
    ->group(function () {

        Route::get('operations/list', [ManufacturingStageController::class, 'index'])
            ->name('operations.list');

        Route::post('operations/{case}/advance', [ManufacturingStageController::class, 'advance'])
            ->name('operations.advance');

        Route::post('operations/{case}/finish-quality', [ManufacturingStageController::class, 'finishQuality'])
            ->name('operations.finish-quality');
    });
