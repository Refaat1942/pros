<?php

use App\Http\Controllers\Dashboard\SpecDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:spec
*/

Route::prefix('spec')->name('spec.')->group(function () {
    // ->middleware('auth:spec')
    Route::get('/', [SpecDashboardController::class, 'index'])->name('dashboard');
});
