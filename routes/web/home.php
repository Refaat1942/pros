<?php

use App\Http\Controllers\Dashboard\HomeController;
use App\Http\Controllers\Patient\PublicTrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Legacy public self-service — disabled (use /track/{uid} from printed QR)
|--------------------------------------------------------------------------
*/
Route::get('/selfservice/{qr}', fn () => abort(404))
    ->middleware('throttle:public');

Route::get('/track/{uid}', [PublicTrackingController::class, 'show'])
    ->middleware('throttle:public')
    ->name('public.track.case');
