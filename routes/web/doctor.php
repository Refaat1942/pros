<?php

use App\Http\Controllers\Dashboard\DoctorDashboardController;
use App\Http\Controllers\MedicalRecord\MedicalRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Doctor Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('doctor', 'doctor.', DoctorDashboardController::class, 'doctor');

/*
|--------------------------------------------------------------------------
| Doctor Medical Exam — JSON endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('doctor')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('doctor.')
    ->group(function () {

        Route::get('queue/list', [MedicalRecordController::class, 'queue'])
            ->name('queue.list');

        Route::get('diagnosis/{appointment}', [MedicalRecordController::class, 'create'])
            ->name('diagnosis.create');

        Route::post('diagnosis', [MedicalRecordController::class, 'store'])
            ->name('diagnosis.store');

        Route::post('records/{record}/lock', [MedicalRecordController::class, 'lock'])
            ->name('records.lock');

        Route::get('records/list', [MedicalRecordController::class, 'index'])
            ->name('records.list');

        Route::get('transfer/list', [MedicalRecordController::class, 'transfers'])
            ->name('transfer.list');
    });
