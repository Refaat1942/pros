<?php

use App\Http\Controllers\Costing\CostingController;
use App\Http\Controllers\Dashboard\CostingDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Costing Dashboard — لوحة مستقلة وبسيطة
|--------------------------------------------------------------------------
*/
registerDashboardPages('costing', 'costing.', CostingDashboardController::class, 'costing');

/*
|--------------------------------------------------------------------------
| Costing API — طابور + مراجعة read-only + تأكيد
|--------------------------------------------------------------------------
*/
Route::prefix('costing')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('costing.')
    ->group(function () {

        Route::get('queue/list', [CostingController::class, 'index'])
            ->name('queue.list');

        Route::get('queue/{case}', [CostingController::class, 'show'])
            ->name('queue.show');

        Route::post('queue/{case}/confirm', [CostingController::class, 'confirm'])
            ->name('queue.confirm');
    });
