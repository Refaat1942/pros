<?php

use App\Http\Controllers\Auth\AuthController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$dashboardPattern = implode('|', Role::ALL_SLUGS);

Route::middleware('guest')->group(function () use ($dashboardPattern) {
    Route::post('/login', [AuthController::class, 'loginUnified'])
        ->middleware('throttle:login')
        ->name('login.submit');

    Route::get('/{dashboard}/login', fn () => redirect('/'))
        ->name('dashboard.login')
        ->where('dashboard', $dashboardPattern);
});

Route::get('/login', fn () => redirect('/'))
    ->name('login')
    ->middleware('guest');

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');
