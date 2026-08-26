<?php

use App\Http\Controllers\Bom\BomController;
use App\Http\Controllers\Bom\ReturnNoteController;
use App\Http\Controllers\Dashboard\TechnicalDashboardController;
use App\Http\Controllers\Quote\QuoteController;
use App\Http\Controllers\Stock\StockCatalogController;
use App\Http\Controllers\Stock\StockReceiveController;
use App\Http\Controllers\Stock\SupplyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Technical Dashboard — Blade pages
| Guard: auth + dashboard.guard (technical role)
|--------------------------------------------------------------------------
*/
registerDashboardPages('technical', 'technical.', TechnicalDashboardController::class, 'technical');
registerDepartmentStaffRoutes('technical', 'technical.', 'technical');

/*
|--------------------------------------------------------------------------
| Warehouse / Inventory — JSON endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('technical')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('technical.')
    ->group(function () {

        // ── Inventory (المخزون) ────────────────────────────────────────────
        Route::middleware('dashboard.page:technical,inventory')->group(function () {
            Route::get('inventory/list', [StockReceiveController::class, 'index'])
                ->name('inventory.list');

            Route::post('inventory/receive', [StockReceiveController::class, 'receive'])
                ->name('inventory.receive');

            Route::get('inventory/{stockItem}/movements', [StockReceiveController::class, 'movements'])
                ->name('inventory.movements');
        });

        Route::middleware('dashboard.page:technical,add-catalog-item')->group(function () {
            Route::post('catalog', [StockCatalogController::class, 'store'])
                ->middleware('can:manage-inventory')
                ->name('catalog.store');
        });

        Route::middleware('dashboard.page:technical,supply-request')->group(function () {
            Route::get('supply/list', [StockReceiveController::class, 'index'])
                ->name('supply.list');

            Route::get('supply/requests', [SupplyRequestController::class, 'index'])
                ->name('supply.requests');

            Route::post('supply/requests', [SupplyRequestController::class, 'store'])
                ->name('supply.requests.store');

            Route::post('supply/requests/{supplyRequestLine}/resolve', [SupplyRequestController::class, 'resolve'])
                ->name('supply.requests.resolve');

            Route::get('supply/search-items', [SupplyRequestController::class, 'searchItems'])
                ->name('supply.search-items');
        });

        Route::middleware('dashboard.page:technical,receive-inbound')->group(function () {
            Route::post('receive/receive', [StockReceiveController::class, 'receive'])
                ->name('receive.receive');

            Route::get('receive/pending-lines', [SupplyRequestController::class, 'index'])
                ->name('receive.pending-lines');
        });

        // ── BOM (خام / تشغيل / تام) ────────────────────────────────────────
        Route::middleware('dashboard.page:technical,bom')->group(function () {
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

            Route::get('bom/{bom}/print-issue-voucher', [BomController::class, 'printIssueVoucher'])
                ->name('bom.print-issue-voucher');

            Route::get('quote/{quote}/print-issue-voucher', [QuoteController::class, 'printIssueVoucher'])
                ->name('quote.print-issue-voucher');
        });

        // ── Return notes — استلام المخزن فقط ───────────────────────────────
        Route::middleware('dashboard.page:technical,returns')->group(function () {
            Route::get('returns/list', [ReturnNoteController::class, 'index'])
                ->name('returns.list');

            Route::post('returns/{returnNote}/complete', [ReturnNoteController::class, 'complete'])
                ->name('returns.complete');
        });
    });
