<?php

/**
 * Register per-page dashboard routes from config/dashboards.php
 */
use Illuminate\Support\Facades\Route;

if (! function_exists('registerDashboardPages')) {
    function registerDashboardPages(string $uriPrefix, string $routeNamePrefix, string $controllerClass, string $configKey): void
    {
        $pages = config("dashboards.{$configKey}.pages", []);
        $default = config("dashboards.{$configKey}.default_page");

        Route::prefix($uriPrefix)->name($routeNamePrefix)->group(function () use ($controllerClass, $pages, $default, $routeNamePrefix) {
            Route::get('/', function () use ($default, $routeNamePrefix) {
                return redirect()->route("{$routeNamePrefix}{$default}");
            })->name('dashboard');

            foreach (array_keys($pages) as $page) {
                Route::get($page, [$controllerClass, 'show'])
                    ->defaults('page', $page)
                    ->name($page);
            }
        });
    }
}
