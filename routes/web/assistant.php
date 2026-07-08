<?php

use App\Http\Controllers\Assistant\AssistantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| المساعد الذكي الإرشادي — بحث في قاعدة معرفة أوفلاين (بدون إنترنت)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/assistant/search', [AssistantController::class, 'search'])
        ->name('assistant.search');
});
