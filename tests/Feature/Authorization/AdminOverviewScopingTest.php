<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminOverviewService;
use App\Services\PermissionCatalogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\Support\StubsBiReportForOverview;
use Tests\TestCase;

class AdminOverviewScopingTest extends TestCase
{
    use ProstheticTestHelper;
    use StubsBiReportForOverview;

    public function test_production_scoped_admin_sees_workshop_not_finance(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'workshop-tracking'),
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk();

        $response->assertSee('تخصيص الإنتاج', false);
        $response->assertSee('id="bi-board-3"', false);
        $response->assertDontSee('id="bi-board-4"', false);
        $response->assertDontSee('محصّل نقدي — الخزنة', false);
        $response->assertDontSee('مديونيات محصّلة', false);
        $response->assertDontSee('id="bi-board-1"', false);
    }

    public function test_finance_scoped_admin_sees_civilian_debt_not_inventory_wac(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'civilian-debts'),
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk();

        $response->assertSee('id="bi-board-4"', false);
        $response->assertSee('مديونيات جهات التعاقد', false);
        $response->assertDontSee('قيمة المخزون — متوسط التكلفة', false);
        $response->assertDontSee('id="bi-board-2"', false);
        $response->assertDontSee('id="bi-board-3"', false);
        $response->assertDontSee('محصّل نقدي — الخزنة', false);
        $response->assertDontSee('مديونيات بانتظار التحصيل', false);
        $response->assertDontSee('التكلفة المجمعة — عسكري', false);
    }

    public function test_civilian_debt_does_not_imply_cashier_queue(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'civilian-debts'),
        ]);

        $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk()
            ->assertDontSee('الخزنة', false)
            ->assertDontSee('بانتظار الدفع النقدي — الخزنة', false)
            ->assertDontSee('data-cycle-key="cashier"', false);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfDay();
        $data = app(AdminOverviewService::class)->pageData($admin, $from, $to);

        $this->assertArrayNotHasKey('awaiting_cashier', $data['case_strip'] ?? []);
        $cycleKeys = collect($data['cycle_cards'] ?? [])->pluck('key')->all();
        $this->assertNotContains('cashier', $cycleKeys);
    }

    public function test_civilian_debt_does_not_imply_military_or_cash_finance_sections(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'civilian-debts'),
        ]);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfDay();
        $data = app(AdminOverviewService::class)->pageData($admin, $from, $to);

        $this->assertArrayHasKey('board4', $data);
        $this->assertArrayHasKey('civilian_debt', $data['board4']);
        $this->assertArrayNotHasKey('military', $data['board4']);
        $this->assertArrayNotHasKey('cash', $data['board4']);
        $this->assertArrayNotHasKey('revenue_cost', $data['board4']);
    }

    public function test_revenue_permission_does_not_imply_cashier_or_military(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            'view-revenue',
        ]);

        $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk()
            ->assertDontSee('data-cycle-key="cashier"', false)
            ->assertDontSee('مديونيات بانتظار التحصيل', false)
            ->assertDontSee('محصّل نقدي — الخزنة', false);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfDay();
        $data = app(AdminOverviewService::class)->pageData($admin, $from, $to);

        $this->assertArrayHasKey('revenue_cost', $data['board4'] ?? []);
        $this->assertArrayNotHasKey('military', $data['board4'] ?? []);
        $this->assertArrayNotHasKey('cash', $data['board4'] ?? []);
        $cycleKeys = collect($data['cycle_cards'] ?? [])->pluck('key')->all();
        $this->assertNotContains('cashier', $cycleKeys);
    }

    public function test_price_only_catalog_user_does_not_see_wac_or_purchasing_board(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'catalog'),
            'view-prices',
        ]);

        $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk()
            ->assertDontSee('id="bi-board-5"', false)
            ->assertDontSee('متوسط التكلفة', false)
            ->assertDontSee('أعلى سعر', false);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfDay();
        $data = app(AdminOverviewService::class)->pageData($admin, $from, $to);

        $this->assertArrayNotHasKey('board5', $data);
        $this->assertArrayNotHasKey('board2', $data);
    }

    public function test_inventory_cost_admin_sees_inventory_revenue_cost_not_debt_or_military(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'inventory-overview'),
            'view-costs',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk();

        $response->assertSee('id="bi-board-2"', false);
        $response->assertSee('المخازن وسلاسل الإمداد', false);
        $response->assertSee('التكلفة التراكمية — مدني', false);
        $response->assertDontSee('مديونيات جهات التعاقد', false);
        $response->assertDontSee('مديونيات بانتظار التحصيل', false);
        $response->assertDontSee('محصّل نقدي — الخزنة', false);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfDay();
        $data = app(AdminOverviewService::class)->pageData($admin, $from, $to);

        $this->assertArrayHasKey('revenue_cost', $data['board4'] ?? []);
        $this->assertArrayNotHasKey('military', $data['board4'] ?? []);
        $this->assertArrayNotHasKey('civilian_debt', $data['board4'] ?? []);
        $this->assertArrayNotHasKey('cash', $data['board4'] ?? []);
    }

    public function test_unauthorized_sensitive_keys_absent_from_backend_html(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'workshop-tracking'),
        ]);

        $html = $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('cash_collected_total', $html);
        $this->assertStringNotContainsString('military_debt_pending', $html);
        $this->assertStringNotContainsString('civilian_cumulative_cost', $html);
        $this->assertStringNotContainsString('bi-board-4', $html);
    }

    public function test_csv_export_respects_same_scope_as_page(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'workshop-tracking'),
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/overview/export')
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('دورة العمل', $csv);
        $this->assertStringNotContainsString('الإيرادات', $csv);
        $this->assertStringNotContainsString('مديونيات', $csv);
        $this->assertStringNotContainsString('صحة المخزون', $csv);
        $this->assertStringNotContainsString('المريض', $csv);
    }

    public function test_civilian_debt_export_lacks_military_cash_and_revenue_rows(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'civilian-debts'),
        ]);

        $csv = $this->actingAs($admin)
            ->get('/admin/overview/export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('مديونيات جهات التعاقد', $csv);
        $this->assertStringNotContainsString('الإيرادات', $csv);
        $this->assertStringNotContainsString('محصّل نقدي', $csv);
        $this->assertStringNotContainsString('مديونيات عسكرية', $csv);
        $this->assertStringNotContainsString('التكلفة المجمعة — عسكري', $csv);
    }

    public function test_price_only_export_lacks_wac_and_margin_columns(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'catalog'),
            'view-prices',
        ]);

        $csv = $this->actingAs($admin)
            ->get('/admin/overview/export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringNotContainsString('WAC', $csv);
        $this->assertStringNotContainsString('صحة المخزون', $csv);
        $this->assertStringNotContainsString('هامش', $csv);
    }

    public function test_super_admin_receives_full_overview(): void
    {
        $this->stubBiReportServiceForOverview();

        app(PermissionCatalogService::class)->syncToDatabase();
        $role = $this->makeRole(Role::SLUG_SUPER_ADMIN);
        $super = User::query()->updateOrCreate(
            ['username' => 'super_overview_scope'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'سوبر أدمن نظرة عامة',
            ],
        );

        $this->actingAs($super)
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="bi-board-1"', false)
            ->assertSee('id="bi-board-2"', false)
            ->assertSee('id="bi-board-3"', false)
            ->assertSee('id="bi-board-4"', false)
            ->assertSee('id="bi-board-5"', false)
            ->assertSee('الاستقبال', false)
            ->assertSee('الخزنة', false);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfDay();
        $data = app(AdminOverviewService::class)->pageData($super, $from, $to);

        $this->assertArrayHasKey('cash', $data['board4']);
        $this->assertArrayHasKey('civilian_debt', $data['board4']);
        $this->assertArrayHasKey('revenue_cost', $data['board4']);
        $this->assertArrayHasKey('military', $data['board4']);
        $this->assertArrayHasKey('contracts_companies', $data['board4']);
    }

    public function test_multi_permission_admin_receives_union_of_widgets(): void
    {
        $admin = $this->limitedAdminWithPages([
            Permission::viewSlug('admin', 'overview'),
            Permission::viewSlug('admin', 'workshop-tracking'),
            Permission::viewSlug('admin', 'catalog'),
            'view-costs',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk();

        $response->assertSee('id="bi-board-2"', false);
        $response->assertSee('id="bi-board-3"', false);
        $response->assertSee('id="bi-board-5"', false);
        $response->assertSee('id="bi-board-4"', false);
        $response->assertSee('التكلفة التراكمية — مدني', false);
        $response->assertDontSee('مديونيات جهات التعاقد', false);
        $response->assertDontSee('مديونيات بانتظار التحصيل', false);
        $response->assertDontSee('محصّل نقدي — الخزنة', false);
    }

    public function test_full_default_admin_permissions_receive_complete_overview(): void
    {
        $this->stubBiReportServiceForOverview();

        app(PermissionCatalogService::class)->syncToDatabase();
        $role = $this->makeRole(Role::SLUG_ADMIN);
        $permissionIds = Permission::query()
            ->where('dashboard', 'admin')
            ->pluck('id')
            ->all();

        foreach (config('permissions.default_actions.admin', []) as $actionSlug) {
            $id = Permission::where('slug', $actionSlug)->value('id');
            if ($id) {
                $permissionIds[] = $id;
            }
        }

        $role->permissions()->sync(array_unique($permissionIds));

        $admin = User::query()->updateOrCreate(
            ['username' => 'admin_full_overview_scope'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'أدمن كامل',
            ],
        );

        $this->actingAs($admin)
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="bi-board-1"', false)
            ->assertSee('id="bi-board-4"', false)
            ->assertSee('الخزنة', false);
    }

    /** @param list<string> $permissionSlugs */
    private function limitedAdminWithPages(array $permissionSlugs): User
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $role = Role::firstOrCreate(
            ['slug' => 'limited_admin_overview_scope'],
            ['label_ar' => 'أدمن اختبار نطاق نظرة عامة'],
        );

        $ids = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($ids);

        return User::query()->updateOrCreate(
            ['username' => 'limited_admin_overview_scope'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'أدمن محدود نظرة عامة',
            ],
        );
    }
}
