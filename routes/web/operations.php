<?php

use App\Http\Controllers\Dashboard\OperationsDashboardController;
use App\Http\Controllers\Manufacturing\ManufacturingStageController;
use App\Http\Controllers\Operations\OperationsDeskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operations Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('operations', 'operations.', OperationsDashboardController::class, 'operations');

/*
|--------------------------------------------------------------------------
| Operations desk (مكتب التشغيل) — decision hub + manufacturing sub-stages
|--------------------------------------------------------------------------
*/
Route::prefix('operations')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('operations.')
    ->group(function () {

        // ── مركز القرار (الخطوة 7) ─────────────────────────────────────────
        Route::get('pending/list', [OperationsDeskController::class, 'pending'])
            ->name('pending.list');

        Route::post('pending/{case}/approve', [OperationsDeskController::class, 'approve'])
            ->name('pending.approve');

        Route::post('pending/{case}/return', [OperationsDeskController::class, 'returnForRework'])
            ->name('pending.return');

        // ── طباعة عرض السعر من مكتب التشغيل ────────────────────────────────
        Route::get('quote/{quote}/print', [\App\Http\Controllers\Quote\QuoteController::class, 'print'])
            ->name('quote.print');

        // ── متابعة التصنيع بعد الصرف ───────────────────────────────────────
        Route::get('operations/list', [ManufacturingStageController::class, 'index'])
            ->name('operations.list');

        Route::post('operations/{case}/advance', [ManufacturingStageController::class, 'advance'])
            ->name('operations.advance');

        Route::post('operations/{case}/finish-quality', [ManufacturingStageController::class, 'finishQuality'])
            ->name('operations.finish-quality');
    });
