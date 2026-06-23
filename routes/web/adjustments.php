<?php

use App\Http\Controllers\Adjustments\AdjustmentsController;
use App\Http\Controllers\Dashboard\AdjustmentsDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Adjustments Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('adjustments', 'adjustments.', AdjustmentsDashboardController::class, 'adjustments');

/*
|--------------------------------------------------------------------------
| Adjustments (المعدلات) — JSON endpoints
| مراجعة بنود الفني (للقراءة) + إضافة بنود + إغلاق ودفع للتكاليف.
|--------------------------------------------------------------------------
*/
Route::prefix('adjustments')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('adjustments.')
    ->group(function () {

        Route::get('adjustments/list', [AdjustmentsController::class, 'index'])
            ->name('adjustments.list');

        Route::get('adjustments/{case}', [AdjustmentsController::class, 'show'])
            ->name('adjustments.show');

        Route::post('adjustments/{case}/items', [AdjustmentsController::class, 'addItems'])
            ->name('adjustments.add-items');

        Route::post('adjustments/{case}/complete', [AdjustmentsController::class, 'complete'])
            ->name('adjustments.complete');
    });
