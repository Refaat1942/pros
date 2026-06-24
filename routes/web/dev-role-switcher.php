<?php

use App\Http\Controllers\Dev\QuickRoleSwitcherController;
use Illuminate\Support\Facades\Route;

Route::post('/dev/switch-role/{role}', [QuickRoleSwitcherController::class, 'switch'])
    ->middleware('auth')
    ->name('dev.role-switch');
