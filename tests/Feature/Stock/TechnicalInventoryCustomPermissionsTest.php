<?php

namespace Tests\Feature\Stock;

use Tests\Support\ProstheticTestCase;

/**
 * يوزر مخزن جديد بصلاحيات مخصّصة (قائمة الأصناف مقفولة له) يفتح صفحة المخزن بدون 500،
 * ونموذج «استلام وارد» يظل يعرض كل الأصناف.
 */
class TechnicalInventoryCustomPermissionsTest extends ProstheticTestCase
{
    public function test_inventory_page_opens_when_item_list_is_disabled_for_the_user(): void
    {
        $item = $this->stockItem('1101', qty: 5);
        $user = $this->userWithRole('technical');
        $user->update(['catalog_list_visibility' => [
            'profiles' => ['technical_inventory' => ['enabled' => false, 'columns' => []]],
        ]]);

        $this->actingAs($user->fresh())->get('/technical/inventory')
            ->assertOk()
            ->assertSee('قائمة المخزن غير مفعّلة', false)
            ->assertSee('value="'.$item->id.'"', false);
    }

    public function test_inventory_page_opens_when_item_list_section_is_disabled_for_the_user(): void
    {
        $item = $this->stockItem('1101', qty: 5);
        $user = $this->userWithRole('technical');
        $sections = array_fill_keys(array_keys(app(\App\Services\CatalogListVisibilityService::class)->sections()), ['enabled' => false]);
        $user->update(['catalog_list_visibility' => ['sections' => $sections]]);

        $this->actingAs($user->fresh())->get('/technical/inventory')
            ->assertOk()
            ->assertSee('value="'.$item->id.'"', false);
    }

    /** نفس صلاحيات «المخزن» في الأوفلاين: صفحات المخزن + صفحات إدارة المخزن، والقائمة مقفولة على مستوى الدور. */
    public function test_offline_stock_role_with_admin_warehouse_pages_and_role_list_disabled(): void
    {
        $item = $this->stockItem('1101', qty: 5);
        $user = $this->userWithRole('technical');
        $extra = ['overview', 'reports', 'catalog', 'inventory-overview', 'dispense-approvals', 'supply-request',
            'receive-inbound', 'stock-categories', 'suppliers', 'stock-kits', 'catalog-list-settings', 'stock-uom-settings'];
        $ids = \App\Models\Permission::whereIn('slug', array_map(fn ($p) => "admin.{$p}.view", $extra))
            ->orWhereIn('slug', ['manage-inventory', 'manage-stock-kits', 'approve-dispense'])
            ->pluck('id');
        $user->role->permissions()->syncWithoutDetaching($ids);

        $this->actingAs($this->userWithRole('admin'))->putJson(route('admin.catalog-list-settings.update'), [
            'roles' => ['technical' => ['technical_inventory' => ['enabled' => false, 'columns' => []]]],
        ])->assertOk();

        $user = $user->fresh(['role']);
        $this->actingAs($user)->get('/technical/inventory')->assertOk()->assertSee('value="'.$item->id.'"', false);
        $this->actingAs($user)->get('/technical/receive-inbound')->assertOk();
        $this->actingAs($user)->get('/technical/supply-request')->assertOk();
        $this->actingAs($user)->getJson('/technical/inventory/list')->assertOk();
    }
}
