<?php

use App\Http\Controllers\Admin\MilitaryRankController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Finance\ContractCompanyController;
use App\Http\Controllers\Finance\CreditNoteController;
use App\Http\Controllers\Finance\DebtController;
use App\Http\Controllers\Pricing\PricingApprovalController;
use App\Http\Controllers\Reports\AdminOverviewController;
use App\Http\Controllers\Reports\AuditLogController;
use App\Http\Controllers\Reports\BiController;
use App\Http\Controllers\Stock\StockCatalogController;
use App\Http\Controllers\Stock\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Dashboard — Blade pages (report pages use dedicated controllers)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('admin.')
    ->group(function () {
        Route::get('overview', [AdminOverviewController::class, 'index'])->name('overview');
        Route::get('bi', [BiController::class, 'index'])->name('bi');
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit');
    });

registerDashboardPages(
    'admin',
    'admin.',
    AdminDashboardController::class,
    'admin',
    except: ['overview', 'bi', 'audit'],
);

/*
|--------------------------------------------------------------------------
| Admin CRUD — JSON API endpoints (auth + dashboard.guard مُرثَّة مسبقاً)
|--------------------------------------------------------------------------
|
| الـ GET (catalog، companies، suppliers) يخدمه registerDashboardPages أعلاه.
| هنا نضيف فقط مسارات الإنشاء، التعديل، والجلب الـ AJAX.
|
*/
Route::prefix('admin')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('admin.')
    ->group(function () {

        // ── Stock Catalog ──────────────────────────────────────────────────
        Route::get('catalog/items', [StockCatalogController::class, 'index'])
            ->name('catalog.items');

        Route::post('catalog', [StockCatalogController::class, 'store'])
            ->name('catalog.store');

        Route::put('catalog/{stockItem}', [StockCatalogController::class, 'update'])
            ->name('catalog.update');

        Route::post('catalog/{stockItem}/prices', [StockCatalogController::class, 'addPrice'])
            ->name('catalog.add-price');

        // ── Contract Companies ─────────────────────────────────────────────
        Route::get('companies/list', [ContractCompanyController::class, 'index'])
            ->name('companies.list');

        Route::post('companies', [ContractCompanyController::class, 'store'])
            ->name('companies.store');

        Route::put('companies/{company}', [ContractCompanyController::class, 'update'])
            ->name('companies.update');

        // ── Suppliers ──────────────────────────────────────────────────────
        Route::get('suppliers/list', [SupplierController::class, 'index'])
            ->name('suppliers.list');

        Route::post('suppliers', [SupplierController::class, 'store'])
            ->name('suppliers.store');

        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])
            ->name('suppliers.update');

        Route::patch('suppliers/{supplier}/toggle', [SupplierController::class, 'toggleActive'])
            ->name('suppliers.toggle');

        // ── Military Ranks — JSON API (الصفحة Blade: GET admin/military-ranks) ──
        Route::get('military-ranks/list', [MilitaryRankController::class, 'index'])
            ->name('military-ranks.list');

        Route::post('military-ranks', [MilitaryRankController::class, 'store'])
            ->name('military-ranks.store');

        Route::put('military-ranks/{militaryRank}', [MilitaryRankController::class, 'update'])
            ->name('military-ranks.update');

        Route::patch('military-ranks/{militaryRank}/toggle', [MilitaryRankController::class, 'toggleActive'])
            ->name('military-ranks.toggle');

        // ── Pricing Approval ───────────────────────────────────────────────
        Route::get('pricing/list', [PricingApprovalController::class, 'index'])
            ->name('pricing.list');

        Route::get('pricing/{pricingRequest}', [PricingApprovalController::class, 'show'])
            ->name('pricing.show');

        Route::post('pricing/{pricingRequest}/approve', [PricingApprovalController::class, 'approve'])
            ->name('pricing.approve');

        // ── Contract debts & payments ────────────────────────────────────
        Route::get('debts/list', [DebtController::class, 'index'])
            ->name('debts.list');

        Route::post('debts/{company}/payment', [DebtController::class, 'recordPayment'])
            ->name('debts.payment');

        // ── Credit notes ─────────────────────────────────────────────────
        Route::get('debts/credit-notes/list', [CreditNoteController::class, 'index'])
            ->name('debts.credit-notes.list');

        Route::post('debts/credit-notes', [CreditNoteController::class, 'store'])
            ->name('debts.credit-notes.store');

        Route::post('debts/credit-notes/{creditNote}/approve', [CreditNoteController::class, 'approve'])
            ->name('debts.credit-notes.approve');

        Route::post('debts/credit-notes/{creditNote}/reject', [CreditNoteController::class, 'reject'])
            ->name('debts.credit-notes.reject');
    });
