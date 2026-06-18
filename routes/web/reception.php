<?php

use App\Http\Controllers\Dashboard\ReceptionDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:reception
*/

Route::prefix('reception')->name('reception.')->group(function () {
    // ->middleware('auth:reception')
    Route::get('/', [ReceptionDashboardController::class, 'index'])->name('dashboard');
});
