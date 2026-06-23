<?php

use App\Http\Controllers\Notifications\DeviceController;
use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Firebase Messaging Service Worker — يُخدَم من جذر الموقع (scope = /)
| يُحقن بإعدادات الويب من .env؛ إن كانت فارغة يعمل كـ no-op.
|--------------------------------------------------------------------------
*/
Route::get('/firebase-messaging-sw.js', function () {
    return response()
        ->view('partials.firebase-sw', ['cfg' => config('firebase.web')])
        ->header('Content-Type', 'application/javascript')
        ->header('Service-Worker-Allowed', '/');
})->name('firebase.sw');

/*
|--------------------------------------------------------------------------
| تسجيل أجهزة المستخدمين (FCM tokens) — للإشعارات Push بين اللوحات
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');

    Route::post('/devices', [DeviceController::class, 'store'])
        ->name('devices.store');
});
