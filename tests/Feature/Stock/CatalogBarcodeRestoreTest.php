<?php

namespace Tests\Feature\Stock;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use App\Services\CatalogListVisibilityService;
use App\Services\PermissionCatalogService;
use App\Services\StockCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class CatalogBarcodeRestoreTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    private function limitedAdminWithCatalogViewOnly(): User
    {
        $role = $this->makeRole(Role::SLUG_ADMIN);
        app(PermissionCatalogService::class)->syncToDatabase();
        app(PermissionCatalogService::class)->seedRoleDefaults(fullSync: true);

        return User::query()->updateOrCreate(
            ['username' => 'limited-admin-barcode'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'أدمن محدود',
            ],
        );
    }

    public function test_filter_item_fields_preserves_alt_codes_and_display_barcode_meta(): void
    {
        $admin = $this->userWithRole('admin');
        $visibility = app(CatalogListVisibilityService::class);

        $visibility->update([
            'roles' => [
                'admin' => [
                    'admin_catalog' => [
                        'enabled' => true,
                        'columns' => ['code', 'name', 'brand'],
                    ],
                ],
            ],
        ]);

        $filtered = $visibility->filterItemFields([
            'id' => 12,
            'code' => 'CAT-12',
            'name' => 'صنف',
            'brand' => 'Ottobock',
            'alt_codes' => '617P37',
            'barcode' => null,
            'display_barcode' => StockItem::barcodeForOperationalCode('617P37'),
            'has_scannable_barcode' => true,
            'category_id' => 2,
            'category' => 'أطراف',
            'min_qty' => 1,
        ], $admin->fresh(), 'admin_catalog');

        $this->assertSame('617P37', $filtered['alt_codes']);
        $this->assertSame(StockItem::barcodeForOperationalCode('617P37'), $filtered['display_barcode']);
        $this->assertTrue($filtered['has_scannable_barcode']);
    }

    public function test_format_item_includes_display_barcode_for_alt_codes_only(): void
    {
        $item = StockItem::create([
            'code' => 'ITM-DISP-1',
            'name' => 'صنف أكواد',
            'alt_codes' => '1H38',
            'barcode' => null,
            'qty' => 0,
            'reserved' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $formatted = app(StockCatalogService::class)->formatItem($item->fresh());

        $this->assertSame(StockItem::barcodeForOperationalCode('1H38'), $formatted['display_barcode']);
        $this->assertTrue($formatted['has_scannable_barcode']);
    }

    public function test_catalog_page_shows_screen_button_for_alt_codes_only_item(): void
    {
        $admin = $this->limitedAdminWithCatalogViewOnly();
        $item = StockItem::create([
            'code' => 'ITM-UI-1',
            'name' => 'هاردنر بودر',
            'alt_codes' => '617P37',
            'barcode' => null,
            'qty' => 1,
            'reserved' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $this->actingAs($admin)
            ->get('/admin/catalog')
            ->assertOk()
            ->assertSee('📱 شاشة', false)
            ->assertSee(route('admin.catalog.screen-barcode', $item), false);
    }

    public function test_catalog_page_shows_screen_button_when_alt_codes_column_hidden(): void
    {
        $admin = $this->limitedAdminWithCatalogViewOnly();
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
                    'admin_catalog' => [
                        'enabled' => true,
                        'columns' => ['code', 'name', 'brand'],
                    ],
                ],
            ],
        ]);

        $item = StockItem::create([
            'code' => 'ITM-HIDDEN-ALT',
            'name' => 'صنف أكواد مخفية',
            'alt_codes' => 'HIDDEN1',
            'barcode' => null,
            'qty' => 1,
            'reserved' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $this->actingAs($admin->fresh())
            ->get('/admin/catalog')
            ->assertOk()
            ->assertSee('/admin/catalog/'.$item->id.'/screen-barcode', false)
            ->assertSee('📱 شاشة', false);
    }

    public function test_screen_and_labels_resolve_to_same_barcode_value(): void
    {
        $admin = $this->userWithRole('admin');
        $item = StockItem::create([
            'code' => 'ITM-SAME-1',
            'name' => 'تطابق شاشة وملصق',
            'alt_codes' => 'SAME99',
            'barcode' => null,
            'qty' => 0,
            'reserved' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $expected = StockItem::barcodeForOperationalCode('SAME99');

        $screen = $this->actingAs($admin)
            ->get(route('admin.catalog.screen-barcode', $item))
            ->assertOk();

        $labels = $this->actingAs($admin)
            ->get(route('admin.catalog.labels', $item))
            ->assertOk();

        $screen->assertSee($expected, false);
        $labels->assertSee($expected, false);
    }

    public function test_labels_skip_item_when_barcode_column_is_non_scannable_garbage(): void
    {
        $admin = $this->userWithRole('admin');
        $item = StockItem::create([
            'code' => 'ITM-GARB-1',
            'name' => 'باركود غير قابل للمسح',
            'alt_codes' => 'GOOD1',
            'barcode' => '٠١٢',
            'qty' => 0,
            'reserved' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $expected = StockItem::barcodeForOperationalCode('GOOD1');

        $this->actingAs($admin)
            ->get(route('admin.catalog.labels', $item))
            ->assertOk()
            ->assertSee($expected, false)
            ->assertDontSee('٠١٢', false);
    }

    public function test_normalize_scannable_barcode_strips_non_ascii(): void
    {
        $this->assertNull(StockItem::normalizeScannableBarcode('٠١٢'));
        $this->assertSame('BC-OK', StockItem::normalizeScannableBarcode('BC-OK'));
    }

    public function test_labels_use_display_barcode_for_alt_codes_only_item(): void
    {
        $admin = $this->userWithRole('admin');
        $item = StockItem::create([
            'code' => 'ITM-LBL-1',
            'name' => 'ملصق أكواد',
            'alt_codes' => 'RM-ALT',
            'barcode' => null,
            'qty' => 0,
            'reserved' => 0,
            'wac' => 0,
            'status' => StockItem::STATUS_OK,
        ]);

        $expected = StockItem::barcodeForOperationalCode('RM-ALT');

        $this->actingAs($admin)
            ->get(route('admin.catalog.labels', $item))
            ->assertOk()
            ->assertSee($expected, false);
    }

    public function test_admin_with_catalog_view_can_print_barcode_via_page_alias(): void
    {
        $admin = $this->limitedAdminWithCatalogViewOnly();

        $this->assertTrue($admin->fresh()->hasPermission('print-barcode'));
        $this->assertTrue($admin->fresh()->can('print-barcode'));
    }

    public function test_seed_role_defaults_includes_print_barcode_for_admin(): void
    {
        $role = $this->makeRole(Role::SLUG_ADMIN);
        app(PermissionCatalogService::class)->syncToDatabase();
        app(PermissionCatalogService::class)->seedRoleDefaults(fullSync: true);

        $slugs = $role->fresh()->permissions()->pluck('slug')->all();

        $this->assertContains('print-barcode', $slugs);
        $this->assertContains(Permission::viewSlug('admin', 'catalog'), $slugs);
    }
}
