<?php

use App\Http\Controllers\Dashboard\AdminDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:admin
*/

Route::prefix('admin')->name('admin.')->group(function () {
    // ->middleware('auth:admin')
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
});
