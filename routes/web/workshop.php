<?php

use App\Http\Controllers\Bom\ReturnNoteController;
use App\Http\Controllers\Admin\WorkshopSectionController;
use App\Http\Controllers\Admin\WorkshopTechnicianController;
use App\Http\Controllers\Dashboard\WorkshopDashboardController;
use App\Http\Controllers\Manufacturing\WorkshopQueueController;
use App\Http\Controllers\Stock\OperationalCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workshop Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('workshop', 'workshop.', WorkshopDashboardController::class, 'workshop');

/*
|--------------------------------------------------------------------------
| قسم الإنتاج — طابور الإنتاج وإتمام التصنيع
|--------------------------------------------------------------------------
*/
Route::prefix('workshop')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('workshop.')
    ->group(function () {
        Route::middleware('dashboard.page:workshop,catalog')->group(function () {
            Route::get('catalog/list', [OperationalCatalogController::class, 'index'])
                ->defaults('profile', 'workshop_catalog')
                ->name('catalog.list');
        });

        Route::middleware(['dashboard.page:workshop,sections', 'can:manage-workshop-sections'])->group(function () {
            Route::get('sections/list', [WorkshopSectionController::class, 'index'])
                ->name('sections.list');
            Route::post('sections', [WorkshopSectionController::class, 'store'])
                ->name('sections.store');
            Route::put('sections/{workshopSection}', [WorkshopSectionController::class, 'update'])
                ->name('sections.update');
            Route::delete('sections/{workshopSection}', [WorkshopSectionController::class, 'destroy'])
                ->name('sections.destroy');

            Route::get('technicians/list', [WorkshopTechnicianController::class, 'index'])
                ->name('technicians.list');
            Route::post('technicians', [WorkshopTechnicianController::class, 'store'])
                ->name('technicians.store');
            Route::put('technicians/{user}', [WorkshopTechnicianController::class, 'update'])
                ->name('technicians.update');
            Route::delete('technicians/{user}', [WorkshopTechnicianController::class, 'destroy'])
                ->name('technicians.destroy');
        });

        Route::middleware('dashboard.page:workshop,workshop')->group(function () {
            Route::get('workshop/list', [WorkshopQueueController::class, 'index'])
                ->name('workshop.list');

            Route::get('workshop-assignment/options', [WorkshopQueueController::class, 'assignmentOptions'])
                ->name('workshop-assignment.options');

            Route::post('workshop/{case}/assign', [WorkshopQueueController::class, 'assign'])
                ->name('workshop.assign');

            Route::get('technicians/board', [WorkshopQueueController::class, 'technicianBoard'])
                ->name('technicians.board');

            Route::post('workshop/{case}/progress', [WorkshopQueueController::class, 'updateProgress'])
                ->name('workshop.progress');

            Route::post('workshop/{case}/advance', [WorkshopQueueController::class, 'advance'])
                ->name('workshop.advance');

            Route::post('workshop/{case}/finish-quality', [WorkshopQueueController::class, 'finishQuality'])
                ->name('workshop.finish-quality');

            Route::get('work-order/{case}/print', [WorkshopQueueController::class, 'printWorkOrder'])
                ->name('work-order.print');
        });

        // ── Return requests (طلب ارتجاع مواد → المخزن) ─────────────────────
        Route::middleware('dashboard.page:workshop,returns')->group(function () {
            Route::get('returns/list', [ReturnNoteController::class, 'index'])
                ->name('returns.list');

            Route::get('returns/create', [ReturnNoteController::class, 'create'])
                ->name('returns.create');

            Route::post('returns', [ReturnNoteController::class, 'store'])
                ->name('returns.store');
        });
    });
