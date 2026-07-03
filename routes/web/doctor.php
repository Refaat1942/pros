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

        // ── Queue (قائمة الانتظار) ─────────────────────────────────────────
        Route::get('queue/list', [MedicalRecordController::class, 'queue'])
            ->middleware('dashboard.page:doctor,queue')
            ->name('queue.list');

        // ── Medical exam API (من قائمة الانتظار) ───────────────────────────
        Route::middleware('dashboard.page:doctor,queue')->group(function () {
            Route::get('diagnosis/{appointment}', [MedicalRecordController::class, 'create'])
                ->name('diagnosis.create');

            Route::post('diagnosis', [MedicalRecordController::class, 'store'])
                ->name('diagnosis.store');

            Route::post('diagnosis/{appointment}/skip', [MedicalRecordController::class, 'skip'])
                ->name('diagnosis.skip');
        });

        Route::get('diagnosis', function () {
            $appointment = request()->query('appointment');

            return redirect()->route('doctor.queue', $appointment ? ['appointment' => $appointment] : []);
        })->name('diagnosis.legacy');

        // ── Records (السجل الطبي) ──────────────────────────────────────────
        Route::middleware('dashboard.page:doctor,records')->group(function () {
            Route::post('records/{record}/lock', [MedicalRecordController::class, 'lock'])
                ->name('records.lock');

            Route::get('records/list', [MedicalRecordController::class, 'index'])
                ->name('records.list');
        });

        // ── Transfer (المحولون للتوصيف) ────────────────────────────────────
        Route::get('transfer/list', [MedicalRecordController::class, 'transfers'])
            ->middleware('dashboard.page:doctor,transfer')
            ->name('transfer.list');
    });
