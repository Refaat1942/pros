<?php

use App\Http\Controllers\Dashboard\WorkshopDashboardController;
use App\Http\Controllers\Manufacturing\WorkshopQueueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workshop Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('workshop', 'workshop.', WorkshopDashboardController::class, 'workshop');

/*
|--------------------------------------------------------------------------
| ورشة التصنيع — طابور الإنتاج وإتمام التصنيع
|--------------------------------------------------------------------------
*/
Route::prefix('workshop')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('workshop.')
    ->group(function () {
        Route::middleware('dashboard.page:workshop,workshop')->group(function () {
            Route::get('workshop/list', [WorkshopQueueController::class, 'index'])
                ->name('workshop.list');

            Route::post('workshop/{case}/advance', [WorkshopQueueController::class, 'advance'])
                ->name('workshop.advance');

            Route::post('workshop/{case}/finish-quality', [WorkshopQueueController::class, 'finishQuality'])
                ->name('workshop.finish-quality');

            Route::get('work-order/{case}/print', [WorkshopQueueController::class, 'printWorkOrder'])
                ->name('work-order.print');
        });
    });
