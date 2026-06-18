<?php

use App\Http\Controllers\Dashboard\DoctorDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Guard (تصميم فقط): auth:doctor
*/

Route::prefix('doctor')->name('doctor.')->group(function () {
    // ->middleware('auth:doctor')
    Route::get('/', [DoctorDashboardController::class, 'index'])->name('dashboard');
});
