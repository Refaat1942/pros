<?php

namespace Tests\Feature\Stock;

use App\Models\Permission;
use App\Models\StockCategory;
use App\Models\StockCategoryField;
use App\Models\StockItem;
use App\Services\PermissionCatalogService;
use App\Services\StockCatalogService;
use Tests\Support\ProstheticTestCase;

class StockUserPriceListAndEditTest extends ProstheticTestCase
{
    public function test_page_granted_from_another_department_shows_in_the_sidebar(): void
    {
        $this->stockItem('1101', qty: 3);
        $user = $this->userWithRole('technical');
        app(PermissionCatalogService::class)->seedRoleDefaults(true);
        $user->role->permissions()->syncWithoutDetaching(Permission::where('slug', 'admin.catalog.view')->pluck('id'));
        $user = $user->fresh(['role']);

        $this->actingAs($user)->get('/technical/inventory')->assertOk()
            ->assertSee('صفحات من أقسام أخرى', false)
            ->assertSee('href="'.route('admin.catalog').'"', false);

        // ومن صفحة الأسعار يقدر يرجع لصفحات المخزن.
        $this->actingAs($user)->get('/admin/catalog')->assertOk()
            ->assertSee('href="'.route('technical.inventory').'"', false);
    }

    public function test_department_user_without_extra_pages_has_no_cross_group(): void
    {
        $user = $this->userWithRole('technical');
        app(PermissionCatalogService::class)->seedRoleDefaults(true);

        $this->actingAs($user->fresh(['role']))->get('/technical/inventory')->assertOk()
            ->assertDontSee('صفحات من أقسام أخرى', false);
    }

    public function test_renaming_an_item_needs_no_category_and_ignores_empty_required_category_fields(): void
    {
        $admin = $this->userWithRole('admin');
        $category = StockCategory::create(['name' => 'ركب']);
        StockCategoryField::create(['category_id' => $category->id, 'field_key' => 'side', 'label' => 'الجهة', 'type' => 'text', 'required' => true, 'sort_order' => 1]);
        $item = $this->stockItem('1101', qty: 3);
        $item->update(['category_id' => $category->id]);

        $r = $this->actingAs($admin)->putJson("/admin/catalog/{$item->id}", [
            'name' => 'اسم جديد', 'category_id' => $category->id, 'attributes' => [],
        ]);
        $r->assertOk();
        $this->assertSame('اسم جديد', $item->fresh()->name);

        $supplier = \App\Models\Supplier::create(['name' => 'مورد ثابت']);
        $item->suppliers()->sync([$supplier->id]);
        $this->actingAs($admin)->putJson("/admin/catalog/{$item->id}", ['name' => 'اسم تالت'])->assertOk();
        $this->assertSame([$supplier->id], $item->fresh()->suppliers()->pluck('suppliers.id')->all(), 'المورد الحالي يبقى');

        $other = $this->stockItem('1102', qty: 1);
        $this->actingAs($admin)->putJson("/admin/catalog/{$other->id}", ['name' => 'بدون قسم'])->assertOk();
        $this->assertSame('بدون قسم', $other->fresh()->name);
    }

    public function test_price_list_is_ordered_by_page_then_item_number(): void
    {
        $make = fn (string $code, ?string $page, string $no) => StockItem::create([
            'code' => $code, 'catalog_number' => $no, 'page_number' => $page, 'name' => $code,
            'qty' => 1, 'reserved' => 0, 'wac' => 1, 'status' => 'ok',
        ]);
        $make('Z-NOPAGE', null, '1');
        $make('P10-1', '10', '1');
        $make('P2-12', '2', '12');
        $make('P2-3', '2', '3');
        $make('P1-5', '1', '5');

        $order = app(StockCatalogService::class)->allItemsForUnifiedLists()->pluck('code')->all();
        $this->assertSame(['P1-5', 'P2-3', 'P2-12', 'P10-1', 'Z-NOPAGE'], $order);

        $dashboard = collect(app(StockCatalogService::class)->listForDashboard())->pluck('code')->all();
        $this->assertSame(['P1-5', 'P2-3', 'P2-12', 'P10-1', 'Z-NOPAGE'], array_values(array_intersect($dashboard, $order)));
    }
}
