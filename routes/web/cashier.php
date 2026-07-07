<?php

use App\Http\Controllers\Cashier\CashierDeskController;
use App\Http\Controllers\Dashboard\CashierDashboardController;
use App\Http\Controllers\Quote\QuoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('cashier', 'cashier.', CashierDashboardController::class, 'cashier');

/*
|--------------------------------------------------------------------------
| Cashier desk (الخزنة) — تحصيل الدفع النقدي لمرضى الكاش
|--------------------------------------------------------------------------
*/
Route::prefix('cashier')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('cashier.')
    ->group(function () {

        Route::middleware('dashboard.page:cashier,payments')->group(function () {
            Route::get('payments/list', [CashierDeskController::class, 'queue'])
                ->name('payments.list');

            Route::post('payments/{case}/confirm', [CashierDeskController::class, 'confirm'])
                ->middleware('can:confirm-cash-payment')
                ->name('payments.confirm');

            Route::get('quote/{quote}/print', [QuoteController::class, 'print'])
                ->name('quote.print');
        });
    });
