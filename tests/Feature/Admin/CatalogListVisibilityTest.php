<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CatalogListVisibilityService;
use App\Services\UserService;
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

    public function test_user_specific_visibility_overrides_role_defaults(): void
    {
        $visibility = app(CatalogListVisibilityService::class);

        $role = $this->makeRole('technical');
        $user = app(UserService::class)->create([
            'name' => 'فني مخزن',
            'username' => 'tech-vis-1',
            'password' => 'secret123',
            'role_id' => $role->id,
            'status' => User::STATUS_ACTIVE,
            'catalog_list_visibility' => [
                'profiles' => [
                    'technical_inventory' => [
                        'enabled' => true,
                        'columns' => ['code', 'name'],
                    ],
                ],
            ],
        ])->fresh(['role']);

        $this->assertTrue($visibility->isListEnabledForUser($user, 'technical_inventory'));
        $columns = $visibility->visibleColumnsForUser($user, 'technical_inventory');
        $this->assertSame(['code', 'name'], $columns);
    }

    public function test_technical_bom_items_respect_user_visibility(): void
    {
        $technical = $this->userWithRole('technical');

        app(CatalogListVisibilityService::class)->update([
            'roles' => [
                'technical' => [
                    'technical_bom_items' => [
                        'enabled' => true,
                        'columns' => ['code', 'name', 'qty'],
                    ],
                ],
            ],
        ]);

        $user = app(UserService::class)->create([
            'name' => 'فني BOM',
            'username' => 'tech-bom-1',
            'password' => 'secret123',
            'role_id' => $technical->role_id,
            'status' => User::STATUS_ACTIVE,
            'catalog_list_visibility' => [
                'profiles' => [
                    'technical_bom_items' => [
                        'enabled' => true,
                        'columns' => ['code', 'name'],
                    ],
                ],
            ],
        ])->fresh(['role']);

        $visibility = app(CatalogListVisibilityService::class);
        $filtered = $visibility->filterItemFields([
            'stock_item_code' => '1001',
            'code' => '1001',
            'name' => 'صنف BOM',
            'brand' => 'Ottobock',
            'qty' => 2,
            'uom' => 'قطعة',
            'issued_qty' => 0,
            'returned_qty' => 0,
            'unit_cost' => 500.00,
        ], $user, 'technical_bom_items');

        $this->assertSame('1001', $filtered['stock_item_code']);
        $this->assertSame('صنف BOM', $filtered['name']);
        $this->assertArrayNotHasKey('qty', $filtered);
        $this->assertArrayNotHasKey('brand', $filtered);
        $this->assertArrayNotHasKey('unit_cost', $filtered);
    }

    public function test_technical_roles_default_columns_exclude_prices(): void
    {
        $technical = $this->userWithRole('technical');
        $visibility = app(CatalogListVisibilityService::class);

        $inventoryColumns = $visibility->visibleColumnsForUser($technical, 'technical_inventory');
        $bomColumns = $visibility->visibleColumnsForUser($technical, 'technical_bom_items');

        foreach (['price', 'wac', 'unit_cost', 'highest_price'] as $priceField) {
            $this->assertNotContains($priceField, $inventoryColumns, 'technical_inventory must not expose '.$priceField);
            $this->assertNotContains($priceField, $bomColumns, 'technical_bom_items must not expose '.$priceField);
        }
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
