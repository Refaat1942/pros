<?php

namespace Tests\Feature\Authorization;

use App\Models\CaseRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminReportsHubService;
use App\Services\PermissionCatalogService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminReportsScopingTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_reports_only_user_cannot_access_financial_section(): void
    {
        $viewer = $this->reportsOnlyUser();
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($viewer)
            ->get("/admin/reports/financial?from={$from}&to={$to}")
            ->assertNotFound();

        $this->actingAs($viewer)
            ->get("/admin/reports/financial/export?from={$from}&to={$to}")
            ->assertNotFound();
    }

    public function test_reports_only_user_cannot_access_cash_income(): void
    {
        $viewer = $this->reportsOnlyUser();
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($viewer)
            ->get("/admin/reports/cash-income?from={$from}&to={$to}")
            ->assertNotFound();

        $this->actingAs($viewer)
            ->get("/admin/reports/cash-income/export?from={$from}&to={$to}")
            ->assertNotFound();
    }

    public function test_reports_only_user_cannot_access_inventory_valuation(): void
    {
        $viewer = $this->reportsOnlyUser();
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($viewer)
            ->get("/admin/reports/inventory-valuation?from={$from}&to={$to}")
            ->assertNotFound();

        $this->actingAs($viewer)
            ->get("/admin/reports/inventory-valuation/export?from={$from}&to={$to}")
            ->assertNotFound();
    }

    public function test_reports_only_user_cannot_access_patient_tracks(): void
    {
        $viewer = $this->reportsOnlyUser();
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($viewer)
            ->get("/admin/reports/patient-tracks?from={$from}&to={$to}")
            ->assertNotFound();
    }

    public function test_reports_only_user_cannot_access_audit(): void
    {
        $viewer = $this->reportsOnlyUser();
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($viewer)
            ->get("/admin/reports/audit?from={$from}&to={$to}")
            ->assertNotFound();

        $this->actingAs($viewer)
            ->get("/admin/reports/audit/export?from={$from}&to={$to}")
            ->assertNotFound();
    }

    public function test_reports_only_index_excludes_sensitive_cards(): void
    {
        $viewer = $this->reportsOnlyUser();

        $this->actingAs($viewer)
            ->get('/admin/reports')
            ->assertOk()
            ->assertDontSee('الإيرادات والمالية', false)
            ->assertDontSee('التحصيل النقدي — الخزنة', false)
            ->assertDontSee('تقييم المخزون', false)
            ->assertDontSee('مسار المرضى', false)
            ->assertDontSee('سجل الرقابة', false)
            ->assertDontSee('المديونات', false);
    }

    public function test_civilian_debt_user_can_access_civilian_debts_report(): void
    {
        $user = $this->limitedAdminWithPermissions([
            Permission::viewSlug('admin', 'reports'),
            Permission::viewSlug('admin', 'civilian-debts'),
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($user)
            ->get("/admin/reports/civilian-debts?from={$from}&to={$to}")
            ->assertOk()
            ->assertSee('المديونات', false);

        $this->actingAs($user)
            ->get("/admin/reports/civilian-debts/export?from={$from}&to={$to}")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_military_profit_permission_required_for_military_profitability_rows(): void
    {
        $from = '2026-06-01';
        $to = '2026-06-30';

        $civCase = $this->caseAtStage($this->civilianPatient($this->civilianCompany()), CaseRecord::STAGE_DELIVERED);
        $civCase->update([
            'delivered_at' => '2026-06-10 10:00:00',
            'quote_total' => 1000,
            'internal_cost' => 400,
        ]);

        $mCase = $this->caseAtStage($this->militaryPatient($this->militaryCompany()), CaseRecord::STAGE_DELIVERED);
        $mCase->update([
            'delivered_at' => '2026-06-12 10:00:00',
            'military_selling_price' => 2000,
            'internal_cost' => 800,
        ]);

        $ops = $this->userWithRole('operations');
        $this->assertFalse(Gate::forUser($ops)->allows('view-military-profit'));

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);

        $this->actingAs($ops);
        $report = $hub->build('profitability', $dates['from'], $dates['to']);

        $this->assertCount(1, $report['rows']);
        $this->assertSame('مدني', $report['rows'][0][2] ?? null);
    }

    public function test_view_costs_allows_opening_balance_report(): void
    {
        $user = $this->limitedAdminWithPermissions([
            Permission::viewSlug('admin', 'reports'),
            'view-costs',
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($user)
            ->get("/admin/reports/opening-balance?from={$from}&to={$to}")
            ->assertOk()
            ->assertSee('رصيد أول المدة', false);
    }

    public function test_view_prices_alone_does_not_allow_inventory_valuation(): void
    {
        $user = $this->limitedAdminWithPermissions([
            Permission::viewSlug('admin', 'reports'),
            Permission::viewSlug('admin', 'catalog'),
            'view-prices',
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($user)
            ->get("/admin/reports/inventory-valuation?from={$from}&to={$to}")
            ->assertNotFound();

        $this->actingAs($user)
            ->get("/admin/reports/item-margins?from={$from}&to={$to}")
            ->assertNotFound();
    }

    public function test_export_authorization_matches_section_access(): void
    {
        $viewer = $this->reportsOnlyUser();
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($viewer)
            ->get("/admin/reports/contracts?from={$from}&to={$to}")
            ->assertNotFound();

        $this->actingAs($viewer)
            ->get("/admin/reports/contracts/export?from={$from}&to={$to}")
            ->assertNotFound();
    }

    public function test_multi_permission_union_allows_inventory_cost_sections(): void
    {
        $user = $this->limitedAdminWithPermissions([
            Permission::viewSlug('admin', 'reports'),
            Permission::viewSlug('admin', 'inventory-overview'),
            'view-costs',
        ]);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($user)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('تقييم المخزون', false)
            ->assertSee('هامش الربح بالأصناف', false)
            ->assertDontSee('التحصيل النقدي — الخزنة', false)
            ->assertDontSee('مسار المرضى', false);

        $this->actingAs($user)
            ->get("/admin/reports/inventory-valuation?from={$from}&to={$to}")
            ->assertOk();
    }

    public function test_super_admin_sees_all_report_sections_on_index(): void
    {
        $super = $this->userWithRole('admin');

        $this->actingAs($super)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('الإيرادات والمالية', false)
            ->assertSee('التحصيل النقدي — الخزنة', false)
            ->assertSee('تقييم المخزون', false)
            ->assertSee('مسار المرضى', false)
            ->assertSee('سجل الرقابة', false)
            ->assertSee('رصيد أول المدة', false);
    }

    private function reportsOnlyUser(): User
    {
        return $this->limitedAdminWithPermissions([
            Permission::viewSlug('admin', 'reports'),
        ]);
    }

    /** @param list<string> $permissionSlugs */
    private function limitedAdminWithPermissions(array $permissionSlugs): User
    {
        app(PermissionCatalogService::class)->syncToDatabase();

        $role = Role::firstOrCreate(
            ['slug' => 'limited_admin_reports_scope'],
            ['label_ar' => 'أدمن اختبار نطاق التقارير'],
        );

        $ids = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($ids);

        return User::query()->updateOrCreate(
            ['username' => 'limited_admin_reports_scope'],
            [
                'role_id' => $role->id,
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'أدمن محدود تقارير',
            ],
        );
    }
}
