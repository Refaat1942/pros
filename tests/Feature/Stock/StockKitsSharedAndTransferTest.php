<?php

namespace Tests\Feature\Stock;

use App\Models\Permission;
use App\Models\StockItem;
use App\Models\StockKit;
use App\Models\StockKitItem;
use App\Services\PermissionCatalogService;
use App\Services\StockKitTransferService;
use Illuminate\Http\UploadedFile;
use Tests\Support\ProstheticTestCase;

/**
 * «عمل المجموعات»: في سايد بار السوبر أدمن، صلاحية اختيارية للمعدلات/الاعتماد/المخزن،
 * أسعار لمن عنده «أسعار البيع»، ونقل المجموعات بين السيرفرات.
 */
class StockKitsSharedAndTransferTest extends ProstheticTestCase
{
    private function kitWithItems(): StockKit
    {
        $a = $this->stockItem('1101', qty: 10);
        $a->update(['price' => 100]);
        $b = $this->stockItem('1102', qty: 10);
        $b->update(['price' => 40]);
        $kit = StockKit::create(['code' => 'KIT-KNEE', 'name' => 'طقم ركبة', 'type' => StockKit::TYPE_ASSEMBLY, 'is_active' => true]);
        StockKitItem::create(['stock_kit_id' => $kit->id, 'stock_item_id' => $a->id, 'qty' => 2, 'sort_order' => 0]);
        StockKitItem::create(['stock_kit_id' => $kit->id, 'stock_item_id' => $b->id, 'qty' => 3, 'sort_order' => 1]);

        return $kit;
    }

    private function deptUser(string $role, array $grant = [])
    {
        $user = $this->userWithRole($role);
        app(PermissionCatalogService::class)->seedRoleDefaults(true);
        if ($grant !== []) {
            $user->role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', $grant)->pluck('id'));
        }

        return $user->fresh(['role']);
    }

    public function test_super_admin_sidebar_shows_kits_under_daily_stock_work(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/admin/catalog')->assertOk()
            ->assertSee('عمل المجموعات (أطقم / مخصصات)', false)
            ->assertSee('href="'.route('admin.stock-kits').'"', false);
        $this->assertContains('stock-kits', config('dashboards.admin.nav_groups.3.pages'));
    }

    public function test_kits_permission_exists_for_adjustments_costing_stock_and_is_off_by_default(): void
    {
        foreach (['adjustments', 'costing', 'technical'] as $role) {
            $this->assertNotNull(Permission::query()->where('slug', "{$role}.stock-kits.view")->first() ?? (app(PermissionCatalogService::class)->syncToDatabase() ?: Permission::where('slug', "{$role}.stock-kits.view")->first()));
            $user = $this->deptUser($role);
            $this->assertFalse($user->canViewDashboardPage($role, 'stock-kits'), "{$role}: مقفولة افتراضياً");
            $this->actingAs($user)->get("/{$role}/stock-kits")->assertForbidden();
            $this->actingAs($user)->getJson('/shared/stock-kits/list')->assertForbidden();
        }

        $matrix = app(PermissionCatalogService::class)->matrixPageData()['dashboards'];
        foreach (['adjustments', 'costing', 'technical'] as $role) {
            $this->assertContains('stock-kits', $matrix[$role]['views']->pluck('page')->all());
        }
    }

    public function test_granted_stock_user_works_with_kits_through_the_shared_api(): void
    {
        $this->kitWithItems();
        $item = StockItem::where('code', 'like', '%1101%')->firstOrFail();
        $user = $this->deptUser('technical', ['technical.stock-kits.view']);

        $this->actingAs($user)->get('/technical/stock-kits')->assertOk()
            ->assertSee('shared\\/stock-kits', false)
            ->assertSee('href="'.route('technical.stock-kits').'"', false);

        $this->actingAs($user)->getJson('/shared/stock-kits/list')->assertOk()
            ->assertJsonPath('data.0.code', 'KIT-KNEE')
            ->assertJsonPath('with_prices', false)
            ->assertJsonMissingPath('data.0.total_price');

        $this->actingAs($user)->postJson('/shared/stock-kits', [
            'name' => 'مخصصات قدم', 'items' => [['stock_item_id' => $item->id, 'qty' => 1]],
        ])->assertSuccessful();
        $this->assertSame(2, StockKit::count());

        // لا يدخل لوحة الإدارة نفسها.
        $this->actingAs($user)->getJson('/admin/stock-kits/list')->assertForbidden();
    }

    public function test_prices_show_the_quote_price_and_kit_total_for_users_allowed_to_see_prices(): void
    {
        $kit = $this->kitWithItems();
        $first = $kit->items()->first()->stockItem;
        $first->prices()->create(['amount' => 120, 'qty' => 5, 'label' => 'دفعة', 'price_ref' => 'P-1']);

        $res = $this->actingAs($this->userWithRole('admin'))->getJson('/admin/stock-kits/list')->assertOk();
        $res->assertJsonPath('with_prices', true);
        // 2 × 120 (أعلى دفعة) + 3 × 40
        $this->assertEquals(360.0, $res->json('data.0.total_price'));
        $this->assertEquals(120.0, $res->json('data.0.items.0.unit_price'));
        $this->assertEquals(240.0, $res->json('data.0.items.0.line_total'));
    }

    public function test_kits_move_between_servers_by_item_code(): void
    {
        $this->kitWithItems();
        $admin = $this->userWithRole('admin');

        $export = $this->actingAs($admin)->get('/admin/stock-kits/export')->assertOk();
        $this->assertStringContainsString('attachment;', $export->headers->get('Content-Disposition'));
        $data = json_decode($export->getContent(), true);
        $this->assertSame(StockKitTransferService::FORMAT, $data['format']);

        // «سيرفر تاني»: نفس الأصناف بـ id مختلفة، صنف ناقص، ومجموعة قديمة بنفس الكود.
        StockKit::query()->delete();
        $old = StockItem::where('code', 'like', '%1102%')->firstOrFail();
        $data['kits'][0]['items'][] = ['item_code' => 'NOT-HERE', 'name' => 'صنف مش موجود', 'qty' => 1];
        StockKit::create(['code' => 'KIT-KNEE', 'name' => 'قديم', 'type' => StockKit::TYPE_ASSEMBLY, 'is_active' => true]);

        $file = UploadedFile::fake()->createWithContent('kits.json', json_encode($data));
        $res = $this->actingAs($admin)->post('/admin/stock-kits/import', ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(['KIT-KNEE'], $res->json('report.updated'));
        $this->assertSame('NOT-HERE', $res->json('report.missing_items.0.item_code'));
        $kit = StockKit::where('code', 'KIT-KNEE')->firstOrFail();
        $this->assertSame('طقم ركبة', $kit->name);
        $this->assertSame([2, 3], $kit->items()->pluck('qty')->all());
        $this->assertTrue($kit->items()->where('stock_item_id', $old->id)->exists());

        $this->actingAs($admin)->post('/admin/stock-kits/import', [
            'file' => UploadedFile::fake()->createWithContent('x.json', '{"hello":1}'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_kits_export_and_import_commands(): void
    {
        $this->kitWithItems();
        $path = storage_path('framework/testing/kits-cmd.json');
        @mkdir(dirname($path), 0777, true);

        $this->artisan('prosthetics:kits-export', ['path' => $path])->assertSuccessful();
        StockKit::query()->delete();
        $this->artisan('prosthetics:kits-import', ['path' => $path])->assertSuccessful();

        $this->assertSame(2, StockKit::where('code', 'KIT-KNEE')->firstOrFail()->items()->count());
        @unlink($path);
    }
}
