<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Services\PermissionCatalogService;
use Tests\Support\ProstheticTestCase;

/** شاشة الصلاحيات مرتّبة بنفس ترتيب السايد بار، ولا تختفي منها أي صفحة. */
class PermissionMatrixSidebarOrderTest extends ProstheticTestCase
{
    public function test_admin_groups_follow_sidebar_groups_and_page_order(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $admin = app(PermissionCatalogService::class)->matrixPageData()['dashboards']['admin'];

        $sidebar = collect(config('dashboards.admin.nav_groups'))
            ->map(fn ($g) => ['label' => $g['label'], 'pages' => $g['pages']]);

        foreach ($sidebar as $i => $group) {
            $this->assertSame($group['label'], $admin['groups'][$i]['label']);
            $expected = array_values(array_filter(
                $group['pages'],
                fn ($p) => Permission::where('slug', "admin.{$p}.view")->exists(),
            ));
            $this->assertSame($expected, $admin['groups'][$i]['views']->pluck('page')->all());
        }

        // كل صفحة إدارة تظهر مرة واحدة بالضبط (المخفية في مجموعة «صفحات فرعية» آخر القائمة).
        $shown = collect($admin['groups'])->flatMap(fn ($g) => $g['views']->pluck('page'))->all();
        $this->assertEqualsCanonicalizing($admin['views']->pluck('page')->all(), $shown);
        $this->assertCount(count(array_unique($shown)), $shown);
    }

    public function test_department_pages_follow_sidebar_order(): void
    {
        app(PermissionCatalogService::class)->syncToDatabase();
        $dashboards = app(PermissionCatalogService::class)->matrixPageData()['dashboards'];

        foreach ($dashboards as $key => $dash) {
            $order = array_keys(config("dashboards.{$key}.pages", []));
            $pages = $dash['views']->pluck('page')->all();
            $this->assertSame(array_values(array_intersect($order, $pages)), $pages, "ترتيب صفحات {$key}");
        }
    }

    public function test_permissions_page_renders(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/admin/permissions')->assertOk()
            ->assertSee('المخزن — العمل اليومي', false);
    }
}
