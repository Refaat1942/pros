<?php

/**
 * Register per-page dashboard routes from config/dashboards.php
 */
use Illuminate\Support\Facades\Route;

if (! function_exists('registerDashboardPages')) {
    /**
     * يسجّل مسارات جميع صفحات لوحة تحكم معينة من config/dashboards.php.
     *
     * كل مجموعة مسارات محمية تلقائيًا بـ:
     *   - auth          → يعيد التوجيه إلى /login إذا لم يكن المستخدم مصادَقًا
     *   - dashboard.guard → يمنع المستخدم من الوصول إلى لوحة دور آخر
     */
    function registerDashboardPages(
        string $uriPrefix,
        string $routeNamePrefix,
        string $controllerClass,
        string $configKey,
        array $except = [],
    ): void {
        $pages   = config("dashboards.{$configKey}.pages", []);
        $default = config("dashboards.{$configKey}.default_page");

        Route::prefix($uriPrefix)
            ->name($routeNamePrefix)
            ->middleware(['auth', 'dashboard.guard'])
            ->group(function () use ($controllerClass, $pages, $default, $routeNamePrefix, $except) {
                Route::get('/', function () use ($default, $routeNamePrefix) {
                    return redirect()->route("{$routeNamePrefix}{$default}");
                })->name('dashboard');

                foreach (array_keys($pages) as $page) {
                    if (in_array($page, $except, true)) {
                        continue;
                    }

                    Route::get($page, [$controllerClass, 'show'])
                        ->defaults('page', $page)
                        ->name($page);
                }
            });
    }
}
