<?php

use App\Http\Controllers\Dashboard\SpecDashboardController;
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

        Route::get('orders/list', [TechOrderSpecController::class, 'index'])
            ->name('orders.list');

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

        Route::get('pricing/list', [TechOrderSpecController::class, 'pricingStatus'])
            ->name('pricing.list');
    });
