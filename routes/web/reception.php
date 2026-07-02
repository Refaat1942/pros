<?php

use App\Http\Controllers\Admin\MilitaryRankController;
use App\Http\Controllers\Admin\VisitTypeController;
use App\Http\Controllers\Appointment\AppointmentController;
use App\Http\Controllers\Dashboard\ReceptionDashboardController;
use App\Http\Controllers\Delivery\DeliveryController;
use App\Http\Controllers\Finance\ContractCompanyController;
use App\Http\Controllers\Patient\PatientController;
use App\Http\Controllers\Patient\ReceptionSelfServiceController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Quote\ApprovalScanController;
use App\Http\Controllers\Quote\OcrExtractController;
use App\Http\Controllers\Quote\QuoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reception Dashboard — Blade pages
|--------------------------------------------------------------------------
*/
registerDashboardPages('reception', 'reception.', ReceptionDashboardController::class, 'reception');

/*
|--------------------------------------------------------------------------
| Reception CRUD — JSON endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('reception')
    ->middleware(['auth', 'dashboard.guard'])
    ->name('reception.')
    ->group(function () {

        // ── Lookup lists (no page guard — shared across pages) ────────────
        Route::get('lookup/military-ranks', [MilitaryRankController::class, 'index'])
            ->name('lookup.military-ranks');

        Route::get('lookup/visit-types', [VisitTypeController::class, 'index'])
            ->name('lookup.visit-types');

        Route::get('lookup/companies', [ContractCompanyController::class, 'index'])
            ->name('lookup.companies');

        // ── Appointments ───────────────────────────────────────────────────
        Route::middleware('dashboard.page:reception,appointments')->group(function () {
            Route::get('appointments/list', [AppointmentController::class, 'index'])
                ->name('appointments.list');

            Route::post('appointments', [AppointmentController::class, 'store'])
                ->name('appointments.store');

            Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])
                ->name('appointments.update');

            Route::patch('appointments/{appointment}/correct', [AppointmentController::class, 'correct'])
                ->name('appointments.correct');

            Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy'])
                ->name('appointments.destroy');

            Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
                ->name('appointments.update-status');
        });

        // ── Patients ───────────────────────────────────────────────────────
        Route::middleware('dashboard.page:reception,patients')->group(function () {
            Route::get('patients/list', [PatientController::class, 'index'])
                ->name('patients.list');

            Route::post('patients', [PatientController::class, 'store'])
                ->name('patients.store');

            Route::get('patients/{patient}', [PatientController::class, 'show'])
                ->name('patients.show');

            Route::get('patients/{patient}/card/print', [PatientController::class, 'printCard'])
                ->name('patients.card.print');

            Route::put('patients/{patient}', [PatientController::class, 'update'])
                ->name('patients.update');
        });

        // ── Quotes + OCR approval scan (civilian only) ─────────────────────
        Route::middleware('dashboard.page:reception,quote')->group(function () {
            Route::get('quote/list', [QuoteController::class, 'index'])
                ->name('quote.list');

            Route::post('quote/{quote}/issue', [QuoteController::class, 'issue'])
                ->name('quote.issue');

            Route::get('quote/{quote}/print', [QuoteController::class, 'print'])
                ->name('quote.print');

            Route::post('ocr/extract', [OcrExtractController::class, 'extract'])
                ->name('ocr.extract');

            Route::post('ocr/process', [\App\Http\Controllers\Quote\OcrApprovalController::class, 'process'])
                ->name('ocr.process');

            Route::post('ocr/scan', [ApprovalScanController::class, 'scan'])
                ->name('ocr.scan');
        });

        // ── Contracts archive (read-only) ──────────────────────────────────
        Route::middleware('dashboard.page:reception,contracts')->group(function () {
            Route::get('contracts/list', [ContractController::class, 'index'])
                ->name('contracts.list');

            Route::get('contracts/{contract}', [ContractController::class, 'show'])
                ->name('contracts.show');

            Route::get('contracts/{contract}/download', [ContractController::class, 'download'])
                ->name('contracts.download');
        });

        // ── Delivery (QR scan close) ───────────────────────────────────────
        Route::middleware('dashboard.page:reception,delivery')->group(function () {
            Route::get('delivery/list', [DeliveryController::class, 'index'])
                ->name('delivery.list');

            Route::post('delivery/scan', [DeliveryController::class, 'scan'])
                ->name('delivery.scan');

            Route::get('delivery/{case}', [DeliveryController::class, 'show'])
                ->name('delivery.show');
        });

        // ── Self-service lookup ────────────────────────────────────────────
        Route::get('selfservice/lookup', [ReceptionSelfServiceController::class, 'lookup'])
            ->middleware('dashboard.page:reception,selfservice')
            ->name('selfservice.lookup');
    });
