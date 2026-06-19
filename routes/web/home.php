<?php

use App\Http\Controllers\Dashboard\HomeController;
use App\Http\Controllers\Patient\SelfServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Self-service status lookup — عام بدون مصادقة
|--------------------------------------------------------------------------
*/
Route::get('/selfservice/{qr}', [SelfServiceController::class, 'status'])
    ->name('selfservice.status');
