<?php

use App\Http\Controllers\Auth\AuthController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

$dashboardPattern = implode('|', Role::ALL_SLUGS);

/*
|--------------------------------------------------------------------------
| Dashboard-specific login routes
| URL: /{dashboard}/login  (e.g. /reception/login, /admin/login)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () use ($dashboardPattern) {

    Route::get('/{dashboard}/login', [AuthController::class, 'showLogin'])
        ->name('dashboard.login')
        ->where('dashboard', $dashboardPattern);

    Route::post('/{dashboard}/login', [AuthController::class, 'login'])
        ->name('dashboard.login.submit')
        ->where('dashboard', $dashboardPattern);
});

/*
|--------------------------------------------------------------------------
| Generic /login fallback — redirects to home (the real entry point)
|--------------------------------------------------------------------------
*/
Route::get('/login', fn () => redirect('/'))
    ->name('login')
    ->middleware('guest');

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/
Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');
