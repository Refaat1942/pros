<?php

namespace Tests\Feature\Authorization;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * كل مسار محمي بـ dashboard.page:{لوحة},{صفحة} يشير لصفحة موجودة — وإلا يُقفل المسار على الجميع
 * عدا السوبر أدمن بصمت (كان رابط «خطاب الموافقة» في الاستقبال يرجع 403 لهذا السبب).
 */
class RoutePagePermissionsExistTest extends TestCase
{
    public function test_every_page_permission_middleware_references_an_existing_page(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'dashboard.page:')) {
                    continue;
                }

                // أزواج قسم,صفحة — dashboard.page:admin,stock-kits,technical,stock-kits = أيٌّ منهما يكفي.
                $parts = explode(',', substr($middleware, strlen('dashboard.page:')));
                if (count($parts) % 2 !== 0) {
                    $missing[] = "عدد معاملات فردي ← {$route->uri()}";
                }
                foreach (array_chunk($parts, 2) as [$dashboard, $page]) {
                    if (config("dashboards.{$dashboard}.pages.{$page}") === null) {
                        $missing[] = "{$dashboard},{$page} ← {$route->uri()}";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), implode("\n", $missing));
    }
}
