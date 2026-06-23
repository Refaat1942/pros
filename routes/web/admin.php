<?php

use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Finance\MilitaryDebtController;
use App\Http\Controllers\Admin\MilitaryRankController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Admin\StockCategoryController;
use App\Http\Controllers\Admin\VisitTypeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Finance\ContractCompanyController;
use App\Http\Controllers\Pricing\PricingApprovalController;
use App\Http\Controllers\Reports\AdminCaseController;
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

        Route::get('cases/{case}/detail', [AdminCaseController::class, 'show'])->name('cases.detail');
        Route::get('cases/{case}/quote', [AdminCaseController::class, 'quotePrint'])->name('cases.quote');
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

        Route::delete('catalog/{stockItem}', [StockCatalogController::class, 'destroy'])
            ->name('catalog.destroy');

        Route::post('catalog/{stockItem}/prices', [StockCatalogController::class, 'addPrice'])
            ->name('catalog.add-price');

        // ── الرفع الجماعي + قالب CSV + طباعة الباركود ───────────────────────
        Route::get('catalog/template', [StockCatalogController::class, 'template'])
            ->middleware('can:import-inventory')
            ->name('catalog.template');

        Route::post('catalog/import', [StockCatalogController::class, 'import'])
            ->middleware('can:import-inventory')
            ->name('catalog.import');

        Route::get('catalog/{stockItem}/labels', [StockCatalogController::class, 'labels'])
            ->middleware('can:print-barcode')
            ->name('catalog.labels');

        // ── مصفوفة الصلاحيات التفصيلية ──────────────────────────────────────
        Route::post('permissions', [PermissionMatrixController::class, 'update'])
            ->middleware('can:manage-permissions')
            ->name('permissions.update');

        // ── Contract Companies ─────────────────────────────────────────────
        Route::get('companies/list', [ContractCompanyController::class, 'index'])
            ->name('companies.list');

        Route::post('companies', [ContractCompanyController::class, 'store'])
            ->name('companies.store');

        Route::put('companies/{company}', [ContractCompanyController::class, 'update'])
            ->name('companies.update');

        Route::delete('companies/{company}', [ContractCompanyController::class, 'destroy'])
            ->name('companies.destroy');

        // ── Suppliers ──────────────────────────────────────────────────────
        Route::get('suppliers/list', [SupplierController::class, 'index'])
            ->name('suppliers.list');

        Route::post('suppliers', [SupplierController::class, 'store'])
            ->name('suppliers.store');

        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])
            ->name('suppliers.update');

        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])
            ->name('suppliers.destroy');

        // ── Military Ranks — JSON API (الصفحة Blade: GET admin/military-ranks) ──
        Route::get('military-ranks/list', [MilitaryRankController::class, 'index'])
            ->name('military-ranks.list');

        Route::post('military-ranks', [MilitaryRankController::class, 'store'])
            ->name('military-ranks.store');

        Route::put('military-ranks/{militaryRank}', [MilitaryRankController::class, 'update'])
            ->name('military-ranks.update');

        Route::delete('military-ranks/{militaryRank}', [MilitaryRankController::class, 'destroy'])
            ->name('military-ranks.destroy');

        // ── Visit Types — JSON API (الصفحة Blade: GET admin/visit-types) ─────
        Route::get('visit-types/list', [VisitTypeController::class, 'index'])
            ->name('visit-types.list');

        Route::post('visit-types', [VisitTypeController::class, 'store'])
            ->name('visit-types.store');

        Route::put('visit-types/{visitType}', [VisitTypeController::class, 'update'])
            ->name('visit-types.update');

        Route::delete('visit-types/{visitType}', [VisitTypeController::class, 'destroy'])
            ->name('visit-types.destroy');

        // ── Stock Categories — JSON API (الصفحة Blade: GET admin/stock-categories) ─
        Route::get('stock-categories/list', [StockCategoryController::class, 'index'])
            ->name('stock-categories.list');

        Route::post('stock-categories', [StockCategoryController::class, 'store'])
            ->name('stock-categories.store');

        Route::put('stock-categories/{stockCategory}', [StockCategoryController::class, 'update'])
            ->name('stock-categories.update');

        Route::delete('stock-categories/{stockCategory}', [StockCategoryController::class, 'destroy'])
            ->name('stock-categories.destroy');

        // ── Pricing Approval ───────────────────────────────────────────────
        Route::get('pricing/list', [PricingApprovalController::class, 'index'])
            ->name('pricing.list');

        Route::get('pricing/{pricingRequest}', [PricingApprovalController::class, 'show'])
            ->name('pricing.show');

        Route::post('pricing/{pricingRequest}/approve', [PricingApprovalController::class, 'approve'])
            ->name('pricing.approve');

        // ── Military sovereign debts ──────────────────────────────────────
        Route::get('military-debts/list', [MilitaryDebtController::class, 'index'])
            ->name('military-debts.list');

        Route::patch('military-debts/{militaryDebt}/status', [MilitaryDebtController::class, 'updateStatus'])
            ->name('military-debts.status');

        Route::delete('military-debts/{militaryDebt}', [MilitaryDebtController::class, 'destroy'])
            ->name('military-debts.destroy');

        // ── Contracts archive — Admin full CRUD ──────────────────────────
        Route::get('contracts/list', [ContractController::class, 'index'])
            ->name('contracts.list');

        Route::get('contracts/{contract}', [ContractController::class, 'show'])
            ->name('contracts.show');

        Route::get('contracts/{contract}/download', [ContractController::class, 'download'])
            ->name('contracts.download');

        Route::put('contracts/{contract}', [ContractController::class, 'update'])
            ->name('contracts.update');

        Route::delete('contracts/{contract}', [ContractController::class, 'destroy'])
            ->name('contracts.destroy');

        // ── Employees (Blade form POST) ───────────────────────────────────
        Route::post('employees', [UserController::class, 'store'])
            ->name('employees.store');

        Route::put('employees/{user}', [UserController::class, 'update'])
            ->name('employees.update');

        Route::patch('employees/{user}/toggle', [UserController::class, 'toggleStatus'])
            ->name('employees.toggle');

        Route::delete('employees/{user}', [UserController::class, 'destroy'])
            ->name('employees.destroy');
    });
