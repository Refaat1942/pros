<?php

/**
 * Web routes — Session Auth + CSRF (monolithic, no separate SPA API).
 *
 * All interactive JSON endpoints live under role prefixes
 * (/reception, /doctor, /spec, /admin, /technical, /operations, …)
 * and use the `web` middleware group.
 */

use Illuminate\Support\Facades\Route;

require __DIR__.'/web/dashboard-routes.php';

foreach ([
    'auth',
    'home',
    'reception',
    'doctor',
    'spec',
    'adjustments',
    'costing',
    'operations',
    'technical',
    'admin',
    'notifications',
    'fallback',
] as $routeFile) {
    Route::group([], base_path("routes/web/{$routeFile}.php"));
}
