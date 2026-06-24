<?php

use App\Http\Controllers\Bom\BomController;
use App\Http\Controllers\Bom\ReturnNoteController;
use App\Http\Controllers\Dashboard\TechnicalDashboardController;
use App\Http\Controllers\Stock\StockReceiveController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Technical Dashboard — Blade pages
| Guard: auth + dashboard.guard (technical role)
|--------------------------------------------------------------------------
*/
registerDashboardPages('technical', 'technical.', TechnicalDashboardController::class, 'technical');

/*
|--------------------------------------------------------------------------
| Warehouse / Inventory — JSON endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('technical')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('technical.')
    ->group(function () {

        Route::get('inventory/list', [StockReceiveController::class, 'index'])
            ->name('inventory.list');

        Route::post('inventory/receive', [StockReceiveController::class, 'receive'])
            ->name('inventory.receive');

        Route::get('inventory/{stockItem}/movements', [StockReceiveController::class, 'movements'])
            ->name('inventory.movements');

        // ── BOM ────────────────────────────────────────────────────────────
        Route::get('bom/list', [BomController::class, 'index'])
            ->name('bom.list');

        Route::get('bom/create/{case}', [BomController::class, 'create'])
            ->name('bom.create');

        Route::post('bom', [BomController::class, 'store'])
            ->name('bom.store');

        Route::get('bom/{bom}', [BomController::class, 'show'])
            ->name('bom.show');

        Route::post('bom/{bom}/dispense', [BomController::class, 'scanDispense'])
            ->name('bom.dispense');

        Route::post('bom/{bom}/finish', [BomController::class, 'closeFinished'])
            ->name('bom.finish');

        Route::get('quote/{quote}/print-issue-voucher', [\App\Http\Controllers\Quote\QuoteController::class, 'printIssueVoucher'])
            ->name('quote.print-issue-voucher');

        // ── Return notes ─────────────────────────────────────────────────────
        Route::get('returns/list', [ReturnNoteController::class, 'index'])
            ->name('returns.list');

        Route::get('returns/create', [ReturnNoteController::class, 'create'])
            ->name('returns.create');

        Route::post('returns', [ReturnNoteController::class, 'store'])
            ->name('returns.store');

        Route::post('returns/{returnNote}/complete', [ReturnNoteController::class, 'complete'])
            ->name('returns.complete');
    });
