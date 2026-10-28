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
        $pages = config("dashboards.{$configKey}.pages", []);
        $default = config("dashboards.{$configKey}.default_page");

        Route::prefix($uriPrefix)
            ->name($routeNamePrefix)
            ->middleware(['auth', 'dashboard.guard'])
            ->group(function () use ($controllerClass, $pages, $default, $routeNamePrefix, $except, $configKey) {
                Route::get('/', function () use ($default, $routeNamePrefix, $configKey, $pages) {
                    $user = auth()->user();

                    if ($user?->canViewDashboardPage($configKey, $default)) {
                        return redirect()->route("{$routeNamePrefix}{$default}");
                    }

                    foreach (array_keys($pages) as $page) {
                        if ($user?->canViewDashboardPage($configKey, $page)) {
                            return redirect()->route("{$routeNamePrefix}{$page}");
                        }
                    }

                    abort(403, 'ليس لديك صلاحية الوصول إلى أي صفحة في هذه اللوحة.');
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

if (! function_exists('registerStockKitApiRoutes')) {
    /** واجهات «عمل المجموعات» — تُسجَّل تحت /admin وتحت /shared. */
    function registerStockKitApiRoutes(): void
    {
        $StockKitController = \App\Http\Controllers\Admin\StockKitController::class;

        // نقل المجموعات بين السيرفرات (أونلاين ← أوفلاين) — ملف JSON بأكواد الأصناف.
        Route::get('stock-kits/export', [$StockKitController, 'export'])
            ->name('stock-kits.export');
        Route::post('stock-kits/import', [$StockKitController, 'import'])
            ->name('stock-kits.import');
        Route::get('stock-kits/templates', [$StockKitController, 'templates'])
            ->name('stock-kits.templates');
        Route::post('stock-kits/groups', [$StockKitController, 'storeGroup'])
            ->name('stock-kits.groups.store');
        Route::delete('stock-kits/groups/{groupKey}', [$StockKitController, 'destroyGroup'])
            ->name('stock-kits.groups.destroy');
        Route::get('stock-kits/search-items', [$StockKitController, 'searchItems'])
            ->name('stock-kits.search-items');
        Route::get('stock-kits/list', [$StockKitController, 'index'])
            ->name('stock-kits.list');
        Route::get('stock-kits/suggestions', [$StockKitController, 'suggestions'])
            ->name('stock-kits.suggestions');
        Route::post('stock-kits/suggestions/dismiss', [$StockKitController, 'dismissSuggestion'])
            ->name('stock-kits.suggestions.dismiss');
        Route::get('stock-kits/expand/{code}', [$StockKitController, 'expand'])
            ->name('stock-kits.expand');
        Route::post('stock-kits', [$StockKitController, 'store'])
            ->name('stock-kits.store');
        Route::put('stock-kits/{stockKit}', [$StockKitController, 'update'])
            ->name('stock-kits.update');
        Route::delete('stock-kits/{stockKit}', [$StockKitController, 'destroy'])
            ->name('stock-kits.destroy');
    }
}
