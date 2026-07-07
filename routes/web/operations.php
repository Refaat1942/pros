<?php

use App\Http\Controllers\Bom\ReturnNoteController;
use App\Http\Controllers\Dashboard\OperationsDashboardController;
use App\Http\Controllers\Manufacturing\ManufacturingStageController;
use App\Http\Controllers\Operations\OperationsDeskController;
use App\Http\Controllers\Quote\QuoteController;
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

        // ── Pending (موافقات التشغيل) ──────────────────────────────────────
        Route::middleware('dashboard.page:operations,pending')->group(function () {
            Route::get('pending/list', [OperationsDeskController::class, 'pending'])
                ->name('pending.list');

            Route::post('pending/{case}/release-quote', [OperationsDeskController::class, 'releaseQuote'])
                ->middleware('can:approve-pricing')
                ->name('pending.release-quote');

            Route::post('pending/{case}/approve', [OperationsDeskController::class, 'approve'])
                ->middleware('can:approve-pricing')
                ->name('pending.approve');

            Route::post('pending/{case}/return', [OperationsDeskController::class, 'returnForRework'])
                ->middleware('can:approve-pricing')
                ->name('pending.return');

            Route::get('quote/{quote}/print', [QuoteController::class, 'print'])
                ->name('quote.print');

            Route::get('case/{case}/print-work-order', [ManufacturingStageController::class, 'printWorkOrder'])
                ->name('work-order.print');
        });

        // ── Quotes awaiting (عروض بانتظار الموافقة) ───────────────────────
        Route::get('quotes-awaiting/list', [OperationsDeskController::class, 'quotesAwaitingApproval'])
            ->middleware('dashboard.page:operations,quotes-awaiting')
            ->name('quotes-awaiting.list');

        Route::redirect('operations', '/reception/delivery');
        Route::redirect('operations/operations', '/reception/delivery');

        // ── Return requests (طلب ارتجاع مواد → المخزن) ─────────────────────
        Route::middleware('dashboard.page:operations,returns')->group(function () {
            Route::get('returns/list', [ReturnNoteController::class, 'index'])
                ->name('returns.list');

            Route::get('returns/create', [ReturnNoteController::class, 'create'])
                ->name('returns.create');

            Route::post('returns', [ReturnNoteController::class, 'store'])
                ->name('returns.store');
        });
    });
