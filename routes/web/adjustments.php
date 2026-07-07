<?php

use App\Http\Controllers\Adjustments\AdjustmentEditRequestController;
use App\Http\Controllers\Adjustments\AdjustmentsController;
use App\Http\Controllers\Adjustments\AdjustmentsHistoryController;
use App\Http\Controllers\Dashboard\AdjustmentsDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Adjustments Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('adjustments', 'adjustments.', AdjustmentsDashboardController::class, 'adjustments', except: ['history']);

Route::prefix('adjustments')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('adjustments.')
    ->group(function () {
        Route::get('history', function () {
            $params = array_filter([
                'from' => request()->query('from'),
                'to' => request()->query('to'),
                'search' => request()->query('search'),
            ], fn ($v) => $v !== null && $v !== '');

            return redirect()->to(route('adjustments.adjustments', $params).'#adj-history-section');
        })->name('history');
    });

/*
|--------------------------------------------------------------------------
| Adjustments (المعدلات) — JSON endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('adjustments')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('adjustments.')
    ->group(function () {

        // ── Adjustments page (جدول المعدلات) ──────────────────────────────
        Route::middleware('dashboard.page:adjustments,adjustments')->group(function () {
            Route::get('adjustments/list', [AdjustmentsController::class, 'index'])
                ->name('adjustments.list');

            Route::get('adjustments/{case}', [AdjustmentsController::class, 'show'])
                ->name('adjustments.show');

            Route::post('adjustments/{case}/items', [AdjustmentsController::class, 'addItems'])
                ->name('adjustments.add-items');

            Route::delete('adjustments/{case}/items/{bomItem}', [AdjustmentsController::class, 'removeItem'])
                ->name('adjustments.remove-item');

            Route::post('adjustments/{case}/complete', [AdjustmentsController::class, 'complete'])
                ->name('adjustments.complete');

            Route::get('adjustments/{case}/edit-request', [AdjustmentEditRequestController::class, 'show'])
                ->name('adjustments.edit-request.show');

            Route::post('adjustments/{case}/edit-request', [AdjustmentEditRequestController::class, 'store'])
                ->name('adjustments.edit-request.store');
        });

        Route::middleware('dashboard.page:adjustments,adjustments')->group(function () {
            Route::get('history/list', [AdjustmentsHistoryController::class, 'index'])
                ->name('history.list');

            Route::get('history/export', [AdjustmentsHistoryController::class, 'export'])
                ->name('history.export');
        });
    });
