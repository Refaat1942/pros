<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CatalogListVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CatalogListVisibilityTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    /** مستخدم بدور admin فعلي — ليس سوبر أدمن (userWithRole('admin') يُعاد تعريفه للاختبارات). */
    private function limitedAdminUser(): User
    {
        $role = $this->makeRole(Role::SLUG_ADMIN);
        app(\App\Services\PermissionCatalogService::class)->syncToDatabase();
        $role->permissions()->syncWithoutDetaching(Permission::query()->pluck('id'));

        return User::query()->updateOrCreate(
            ['username' => 'limited-admin-catalog'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => $role->label_ar,
            ],
        );
    }

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

    public function test_section_master_toggle_disables_inventory_supply_lists(): void
    {
        $admin = $this->limitedAdminUser();

        $this->actingAs($this->userWithRole('admin'))->putJson(route('admin.catalog-list-settings.update'), [
            'sections' => [
                'inventory_supply' => [
                    'roles' => [
                        'admin' => ['enabled' => false],
                    ],
                ],
            ],
            'roles' => [
                'admin' => [
                    'admin_catalog' => [
                        'enabled' => true,
                        'columns' => ['code', 'name', 'brand'],
                    ],
                ],
            ],
        ])->assertOk();

        $adminUser = $admin->fresh();
        $visibility = app(CatalogListVisibilityService::class);

        $this->assertFalse($visibility->isSectionEnabledForUser($adminUser, 'inventory_supply'));
        $this->assertFalse($visibility->isListEnabledForUser($adminUser, 'admin_catalog'));
        $this->assertSame([], $visibility->visibleColumnsForUser($adminUser, 'admin_catalog'));
    }

    public function test_stock_kit_search_blocked_when_picker_list_disabled(): void
    {
        $admin = $this->limitedAdminUser();

        app(CatalogListVisibilityService::class)->update([
            'sections' => [
                'inventory_supply' => [
                    'roles' => [
                        'admin' => ['enabled' => true],
                    ],
                ],
            ],
            'roles' => [
                'admin' => [
                    'stock_kits_picker' => [
                        'enabled' => false,
                        'columns' => ['code', 'name'],
                    ],
                ],
            ],
        ]);

        $this->actingAs($admin->fresh())
            ->getJson('/admin/stock-kits/search-items?q=test')
            ->assertForbidden()
            ->assertJsonFragment(['data' => []]);
    }

    public function test_stock_kit_search_filters_columns(): void
    {
        $admin = $this->limitedAdminUser();
        $item = $this->stockItem('KIT-TEST-1');
        $item->update(['name' => 'صنف طقم', 'brand' => 'Ottobock', 'uom' => 'قطعة']);

        app(CatalogListVisibilityService::class)->update([
            'sections' => [
                'inventory_supply' => [
                    'roles' => [
                        'admin' => ['enabled' => true],
                    ],
                ],
            ],
            'roles' => [
                'admin' => [
                    'stock_kits_picker' => [
                        'enabled' => true,
                        'columns' => ['code', 'name'],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($admin->fresh())
            ->getJson('/admin/stock-kits/search-items?q='.urlencode($item->code));

        $response->assertOk();
        $first = collect($response->json('data'))->firstWhere('code', $item->pickerCode())
            ?? $response->json('data.0');
        $this->assertNotNull($first);
        $this->assertSame('صنف طقم', $first['name']);
        $this->assertArrayNotHasKey('brand', $first);
        $this->assertArrayNotHasKey('uom', $first);
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
