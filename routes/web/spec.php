<?php

use App\Http\Controllers\Dashboard\SpecDashboardController;
use App\Http\Controllers\Spec\SpecEditRequestController;
use App\Http\Controllers\TechOrderSpec\TechOrderSpecController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Spec Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('spec', 'spec.', SpecDashboardController::class, 'spec');

/*
|--------------------------------------------------------------------------
| Technical Specification — JSON endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('spec')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('spec.')
    ->group(function () {

        // ── Orders (طلبات التوصيف) ─────────────────────────────────────────
        Route::get('orders/list', [TechOrderSpecController::class, 'index'])
            ->middleware('dashboard.page:spec,orders')
            ->name('orders.list');

        Route::get('orders/export', [TechOrderSpecController::class, 'exportOrders'])
            ->middleware('dashboard.page:spec,orders')
            ->name('orders.export');

        // ── Spec (معاينة التوصيف) ──────────────────────────────────────────
        Route::middleware('dashboard.page:spec,spec')->group(function () {
            Route::get('spec/{case}', [TechOrderSpecController::class, 'create'])
                ->name('spec.create');

            Route::post('spec', [TechOrderSpecController::class, 'store'])
                ->name('spec.store');

            Route::put('spec/{spec}', [TechOrderSpecController::class, 'update'])
                ->name('spec.update');

            Route::post('spec/{spec}/submit', [TechOrderSpecController::class, 'submit'])
                ->name('spec.submit');

            Route::get('spec/{spec}/preview', [TechOrderSpecController::class, 'preview'])
                ->name('spec.preview');

            Route::get('spec/{spec}/print', [TechOrderSpecController::class, 'print'])
                ->name('spec.print');

            Route::get('spec/{spec}/edit-request', [SpecEditRequestController::class, 'show'])
                ->name('spec.edit-request.show');

            Route::post('spec/{spec}/edit-request', [SpecEditRequestController::class, 'store'])
                ->name('spec.edit-request.store');
        });

        // ── Pricing status (إرسال للتسعير) ────────────────────────────────
        Route::get('pricing/list', [TechOrderSpecController::class, 'pricingStatus'])
            ->middleware('dashboard.page:spec,pricing')
            ->name('pricing.list');
    });
