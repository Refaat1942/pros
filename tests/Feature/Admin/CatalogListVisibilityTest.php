<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Services\CatalogListVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CatalogListVisibilityTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    public function test_admin_can_update_catalog_list_visibility_for_role(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->putJson(route('admin.catalog-list-settings.update'), [
            'roles' => [
                'technical' => [
                    'technical_inventory' => [
                        'enabled' => true,
                        'columns' => ['code', 'name', 'brand', 'available'],
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $technical = $this->userWithRole('technical')->fresh();
        $visibility = app(CatalogListVisibilityService::class);

        $this->assertTrue($visibility->isListEnabledForUser($technical, 'technical_inventory'));
        $columns = $visibility->visibleColumnsForUser($technical, 'technical_inventory');
        $this->assertContains('code', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('brand', $columns);
        $this->assertContains('available', $columns);
        $this->assertNotContains('price', $columns);
    }

    public function test_filter_item_fields_respects_visible_columns(): void
    {
        $technical = $this->userWithRole('technical');

        app(CatalogListVisibilityService::class)->update([
            'roles' => [
                'technical' => [
                    'technical_inventory' => [
                        'enabled' => true,
                        'columns' => ['code', 'name', 'brand', 'available'],
                    ],
                ],
            ],
        ]);

        $visibility = app(CatalogListVisibilityService::class);
        $filtered = $visibility->filterItemFields([
            'id' => 1,
            'code' => 'RM-1',
            'name' => 'صنف',
            'brand' => 'Ottobock',
            'uom' => 'قطعة',
            'available' => 5,
            'status' => 'ok',
            'qty' => 10,
        ], $technical->fresh(), 'technical_inventory');

        $this->assertSame('RM-1', $filtered['code']);
        $this->assertSame('Ottobock', $filtered['brand']);
        $this->assertArrayNotHasKey('uom', $filtered);
        $this->assertArrayNotHasKey('status', $filtered);
    }

    public function test_catalog_list_settings_page_loads_for_admin(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/catalog-list-settings')
            ->assertOk()
            ->assertSee('عرض قوائم الأصناف');
    }
}
