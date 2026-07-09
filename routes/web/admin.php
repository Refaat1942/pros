<?php

use App\Http\Controllers\Admin\BrandingSettingsController;
use App\Http\Controllers\Admin\CostingSettingsController;
use App\Http\Controllers\Admin\MilitaryRankController;
use App\Http\Controllers\Admin\PathwaySettingsController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Admin\SpecEditRequestController as AdminSpecEditRequestController;
use App\Http\Controllers\Admin\StockCategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisitTypeController;
use App\Http\Controllers\Admin\WorkflowSettingsController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Finance\CivilianDebtController;
use App\Http\Controllers\Finance\ContractCompanyController;
use App\Http\Controllers\Finance\MilitaryDebtController;
use App\Http\Controllers\Reports\AdminCaseController;
use App\Http\Controllers\Reports\AdminOverviewController;
use App\Http\Controllers\Reports\AdminReportsHubController;
use App\Http\Controllers\Reports\AuditLogController;
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
        Route::redirect('general-view', '/admin/overview', 301)->name('general-view');
        Route::redirect('bi', '/admin/overview#overview-bi', 301)->name('bi');

        Route::middleware('dashboard.page:admin,overview')->group(function () {
            Route::get('overview', [AdminOverviewController::class, 'index'])->name('overview');
            Route::get('overview/export', [AdminOverviewController::class, 'export'])->name('overview.export');
            Route::get('patient-tracks/list', [AdminOverviewController::class, 'patientTracksApi'])->name('patient-tracks.list');
        });

        Route::get('audit', [AuditLogController::class, 'index'])
            ->middleware('dashboard.page:admin,audit')->name('audit');

        Route::middleware('dashboard.page:admin,reports')->group(function () {
            Route::get('reports', [AdminReportsHubController::class, 'index'])->name('reports');
            Route::get('reports/{section}/export', [AdminReportsHubController::class, 'export'])->name('reports.export');
            Route::get('reports/{section}', [AdminReportsHubController::class, 'show'])->name('reports.section');
        });

        Route::middleware('dashboard.page:admin,cases')->group(function () {
            Route::get('cases/{case}/detail', [AdminCaseController::class, 'show'])->name('cases.detail');
            Route::get('cases/{case}/quote', [AdminCaseController::class, 'quotePrint'])->name('cases.quote');
            Route::post('cases/{case}/workflow/skip', [AdminCaseController::class, 'skipStage'])
                ->name('cases.workflow.skip');
        });
    });

registerDashboardPages(
    'admin',
    'admin.',
    AdminDashboardController::class,
    'admin',
    except: ['overview', 'bi', 'audit', 'reports', 'reports-section', 'general-view'],
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
        Route::middleware('dashboard.page:admin,catalog')->group(function () {
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

            // ── الرفع الجماعي + قالب CSV + طباعة الباركود ───────────────────
            Route::get('catalog/template', [StockCatalogController::class, 'template'])
                ->middleware('can:import-inventory')
                ->name('catalog.template');

            Route::get('catalog/export', [StockCatalogController::class, 'export'])
                ->name('catalog.export');

            Route::post('catalog/import', [StockCatalogController::class, 'import'])
                ->middleware('can:import-inventory')
                ->name('catalog.import');

            Route::get('catalog/labels', [StockCatalogController::class, 'labelsBulk'])
                ->middleware('can:print-barcode')
                ->name('catalog.labels.bulk');

            Route::get('catalog/{stockItem}/labels', [StockCatalogController::class, 'labels'])
                ->middleware('can:print-barcode')
                ->name('catalog.labels');

            Route::get('catalog/{stockItem}/sales-stats', [StockCatalogController::class, 'salesStats'])
                ->name('catalog.sales-stats');
        });

        // ── طلبات تعديل التوصيف ─────────────────────────────────────────────
        Route::middleware('dashboard.page:admin,spec-edit-requests')->group(function () {
            Route::get('spec-edit-requests/list', [AdminSpecEditRequestController::class, 'index'])
                ->name('spec-edit-requests.list');

            Route::post('spec-edit-requests/{specEditRequest}/approve', [AdminSpecEditRequestController::class, 'approve'])
                ->name('spec-edit-requests.approve');

            Route::post('spec-edit-requests/{specEditRequest}/reject', [AdminSpecEditRequestController::class, 'reject'])
                ->name('spec-edit-requests.reject');
        });

        // ── مصفوفة الصلاحيات التفصيلية ──────────────────────────────────────
        Route::middleware('dashboard.page:admin,permissions')->group(function () {
            Route::post('permissions', [PermissionMatrixController::class, 'update'])
                ->middleware('can:manage-permissions')
                ->name('permissions.update');
        });

        // ── Contract Companies ─────────────────────────────────────────────
        Route::middleware('dashboard.page:admin,companies')->group(function () {
            Route::get('companies/list', [ContractCompanyController::class, 'index'])
                ->name('companies.list');

            Route::post('companies', [ContractCompanyController::class, 'store'])
                ->name('companies.store');

            Route::put('companies/{company}', [ContractCompanyController::class, 'update'])
                ->name('companies.update');

            Route::delete('companies/{company}', [ContractCompanyController::class, 'destroy'])
                ->name('companies.destroy');
        });

        // ── Suppliers ──────────────────────────────────────────────────────
        Route::middleware('dashboard.page:admin,suppliers')->group(function () {
            Route::get('suppliers/list', [SupplierController::class, 'index'])
                ->name('suppliers.list');

            Route::get('suppliers/export', [SupplierController::class, 'export'])
                ->name('suppliers.export');

            Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])
                ->name('suppliers.show');

            Route::post('suppliers', [SupplierController::class, 'store'])
                ->name('suppliers.store');

            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])
                ->name('suppliers.update');

            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])
                ->name('suppliers.destroy');
        });

        // ── Military Ranks — JSON API (الصفحة Blade: GET admin/military-ranks) ──
        Route::middleware('dashboard.page:admin,military-ranks')->group(function () {
            Route::get('military-ranks/list', [MilitaryRankController::class, 'index'])
                ->name('military-ranks.list');

            Route::post('military-ranks/reorder', [MilitaryRankController::class, 'reorder'])
                ->name('military-ranks.reorder');

            Route::post('military-ranks', [MilitaryRankController::class, 'store'])
                ->name('military-ranks.store');

            Route::put('military-ranks/{militaryRank}', [MilitaryRankController::class, 'update'])
                ->name('military-ranks.update');

            Route::delete('military-ranks/{militaryRank}', [MilitaryRankController::class, 'destroy'])
                ->name('military-ranks.destroy');
        });

        // ── Costing overhead settings ───────────────────────────────────────
        Route::middleware('dashboard.page:admin,costing-settings')->group(function () {
            Route::put('costing-settings', [CostingSettingsController::class, 'update'])
                ->name('costing-settings.update');

            Route::put('costing-modes', [CostingSettingsController::class, 'updateModes'])
                ->name('costing-modes.update');
        });

        Route::middleware('dashboard.page:admin,branding-settings')->group(function () {
            Route::put('branding-settings', [BrandingSettingsController::class, 'update'])
                ->name('branding-settings.update');
        });

        Route::middleware('dashboard.page:admin,pathway-settings')->group(function () {
            Route::put('pathway-settings', [PathwaySettingsController::class, 'update'])
                ->name('pathway-settings.update');

            Route::post('pathway-settings/reset', [PathwaySettingsController::class, 'reset'])
                ->name('pathway-settings.reset');

            Route::put('workflow-policies', [WorkflowSettingsController::class, 'update'])
                ->name('workflow-policies.update');

            Route::post('workflow-policies/reset', [WorkflowSettingsController::class, 'reset'])
                ->name('workflow-policies.reset');
        });

        // ── Visit Types — JSON API (الصفحة Blade: GET admin/visit-types) ─────
        Route::middleware('dashboard.page:admin,visit-types')->group(function () {
            Route::get('visit-types/list', [VisitTypeController::class, 'index'])
                ->name('visit-types.list');

            Route::post('visit-types', [VisitTypeController::class, 'store'])
                ->name('visit-types.store');

            Route::put('visit-types/{visitType}', [VisitTypeController::class, 'update'])
                ->name('visit-types.update');

            Route::delete('visit-types/{visitType}', [VisitTypeController::class, 'destroy'])
                ->name('visit-types.destroy');

            Route::post('visit-types/reorder', [VisitTypeController::class, 'reorder'])
                ->name('visit-types.reorder');
        });

        // ── Stock Categories (أقسام الأصناف + حقول ديناميكية) ───────────────
        Route::middleware('dashboard.page:admin,stock-categories')->group(function () {
            Route::get('stock-categories/list', [StockCategoryController::class, 'index'])
                ->name('stock-categories.list');

            Route::post('stock-categories', [StockCategoryController::class, 'store'])
                ->name('stock-categories.store');

            Route::put('stock-categories/{stockCategory}', [StockCategoryController::class, 'update'])
                ->name('stock-categories.update');

            Route::delete('stock-categories/{stockCategory}', [StockCategoryController::class, 'destroy'])
                ->name('stock-categories.destroy');
        });

        // ── Civilian contract company debts ───────────────────────────────
        Route::middleware('dashboard.page:admin,civilian-debts')->group(function () {
            Route::get('civilian-debts/list', [CivilianDebtController::class, 'index'])
                ->name('civilian-debts.list');

            Route::post('civilian-debts/{company}/collect', [CivilianDebtController::class, 'recordPayment'])
                ->name('civilian-debts.collect');

            Route::get('civilian-debts/{company}/collections', [CivilianDebtController::class, 'collectionHistory'])
                ->name('civilian-debts.collections');
        });

        // ── Military sovereign debts ──────────────────────────────────────
        Route::middleware('dashboard.page:admin,military-debts')->group(function () {
            Route::get('military-debts/list', [MilitaryDebtController::class, 'index'])
                ->name('military-debts.list');

            Route::patch('military-debts/{militaryDebt}/status', [MilitaryDebtController::class, 'updateStatus'])
                ->name('military-debts.status');

            Route::post('military-debts/{militaryDebt}/collect', [MilitaryDebtController::class, 'recordPayment'])
                ->name('military-debts.collect');

            Route::get('military-debts/{militaryDebt}/collections', [MilitaryDebtController::class, 'collectionHistory'])
                ->name('military-debts.collections');

            Route::delete('military-debts/{militaryDebt}', [MilitaryDebtController::class, 'destroy'])
                ->name('military-debts.destroy');
        });

        // ── Contracts archive — Admin full CRUD ──────────────────────────
        Route::middleware('dashboard.page:admin,contracts')->group(function () {
            Route::get('contracts/list', [ContractController::class, 'index'])
                ->name('contracts.list');

            Route::get('contracts/{contract}', [ContractController::class, 'show'])
                ->name('contracts.show');

            Route::get('contracts/{contract}/letter', [ContractController::class, 'letter'])
                ->name('contracts.letter');

            Route::get('contracts/{contract}/download', [ContractController::class, 'download'])
                ->name('contracts.download');

            Route::put('contracts/{contract}', [ContractController::class, 'update'])
                ->name('contracts.update');

            Route::delete('contracts/{contract}', [ContractController::class, 'destroy'])
                ->name('contracts.destroy');
        });

        // ── Employees (Blade form POST) ───────────────────────────────────
        Route::middleware('dashboard.page:admin,employees')->group(function () {
            Route::post('employees', [UserController::class, 'store'])
                ->name('employees.store');

            Route::put('employees/{user}', [UserController::class, 'update'])
                ->name('employees.update');

            Route::patch('employees/{user}/toggle', [UserController::class, 'toggleStatus'])
                ->name('employees.toggle');

            Route::delete('employees/{user}', [UserController::class, 'destroy'])
                ->name('employees.destroy');
        });
    });
