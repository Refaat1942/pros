<?php

use App\Http\Controllers\Dashboard\OperationsDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:operations
*/

Route::prefix('operations')->name('operations.')->group(function () {
    // ->middleware('auth:operations')
    Route::get('/', [OperationsDashboardController::class, 'index'])->name('dashboard');
});
