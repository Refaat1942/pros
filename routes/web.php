<?php

/**
 * Web routes — Session Auth + CSRF (monolithic, no separate SPA API).
 *
 * All interactive JSON endpoints live under role prefixes
 * (/reception, /doctor, /spec, /admin, /technical, /operations, …)
 * and use the `web` middleware group.
 */

use Illuminate\Support\Facades\Route;

// شعار المركز المرفوع يُخدم مباشرة من التخزين — لا يعتمد على رابط public/storage
// (storage:link يفشل كثيراً على ويندوز/Laragon فيظهر الشعار مكسوراً).
Route::get('/branding/logo', function () {
    $path = app(\App\Services\SettingService::class)->branding()['logo_path'] ?? '';
    $relative = str_starts_with($path, 'storage/') ? substr($path, strlen('storage/')) : null;
    abort_unless($relative !== null && \Illuminate\Support\Facades\Storage::disk('public')->exists($relative), 404);

    return \Illuminate\Support\Facades\Storage::disk('public')->response($relative, null, [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('branding.logo');

require __DIR__.'/web/dashboard-routes.php';
require __DIR__.'/web/department-staff.php';

foreach ([
    'auth',
    'home',
    'reception',
    'doctor',
    'spec',
    'adjustments',
    'costing',
    'operations',
    'cashier',
    'workshop',
    'technical',
    'admin',
    'assistant',
    'notifications',
    'fallback',
] as $routeFile) {
    Route::group([], base_path("routes/web/{$routeFile}.php"));
}
