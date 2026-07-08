<?php

namespace Tests\Feature\Finance;

use App\Models\CaseRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminReportsHubService;
use App\Services\PermissionCatalogService;
use Illuminate\Support\Facades\Gate;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class FinanceReportsAccessTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_sees_finance_report_cards_in_hub(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('رصيد أول المدة', false)
            ->assertSee('رصيد آخر المدة', false)
            ->assertSee('مراجعة التكاليف والربحية', false);
    }

    public function test_non_admin_cannot_access_admin_reports_hub(): void
    {
        foreach (['reception', 'operations'] as $roleSlug) {
            $this->actingAs($this->userWithRole($roleSlug))
                ->get('/admin/reports')
                ->assertForbidden();
        }
    }

    public function test_non_admin_cannot_access_finance_report_sections(): void
    {
        $reception = $this->userWithRole('reception');
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        foreach (['opening-balance', 'closing-balance', 'profitability'] as $section) {
            $this->actingAs($reception)
                ->get("/admin/reports/{$section}?from={$from}&to={$to}")
                ->assertForbidden();
        }
    }

    public function test_finance_cards_hidden_without_view_costs_permission(): void
    {
        $role = Role::firstOrCreate(['slug' => 'finance-viewer'], ['label_ar' => 'مراجع مالي']);
        app(PermissionCatalogService::class)->syncToDatabase();

        $reportsPerm = Permission::query()
            ->where('slug', Permission::viewSlug('admin', 'reports'))
            ->firstOrFail();

        $role->permissions()->sync([$reportsPerm->id]);

        $viewer = User::query()->updateOrCreate(
            ['username' => 'finance-viewer'],
            [
                'role_id' => $role->id,
                'password' => bcrypt('password'),
                'status' => User::STATUS_ACTIVE,
                'name' => 'مراجع مالي',
            ],
        );

        $this->assertFalse(Gate::forUser($viewer)->allows('view-costs'));

        $this->actingAs($viewer)
            ->get('/admin/reports')
            ->assertOk()
            ->assertDontSee('رصيد أول المدة', false)
            ->assertDontSee('مراجعة التكاليف والربحية', false);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($viewer)
            ->get("/admin/reports/opening-balance?from={$from}&to={$to}")
            ->assertNotFound();
    }

    public function test_admin_with_view_costs_can_access_finance_reports(): void
    {
        $admin = $this->userWithRole('admin');
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->assertTrue(Gate::forUser($admin)->allows('view-costs'));

        $this->actingAs($admin)
            ->get('/admin/reports/opening-balance?from='.$from.'&to='.$to)
            ->assertOk()
            ->assertSee('رصيد أول المدة', false)
            ->assertSee('الخزنة النقدية', false);

        $this->actingAs($admin)
            ->get('/admin/reports/closing-balance?from='.$from.'&to='.$to)
            ->assertOk()
            ->assertSee('حركة الفترة', false);

        $this->actingAs($admin)
            ->get('/admin/reports/profitability?from='.$from.'&to='.$to)
            ->assertOk()
            ->assertSee('مراجعة التكاليف والربحية', false);
    }

    public function test_opening_and_closing_balance_reports_render_domain_rows(): void
    {
        $admin = $this->userWithRole('admin');
        $from = '2026-06-01';
        $to = '2026-06-30';

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);

        $opening = $hub->build('opening-balance', $dates['from'], $dates['to']);
        $this->assertSame('رصيد أول المدة', $opening['title']);
        $this->assertSame(['المجال', 'رصيد أول المدة'], $opening['headers']);
        $this->assertNotEmpty($opening['summary']);
        $this->assertNotEmpty($opening['rows']);

        $closing = $hub->build('closing-balance', $dates['from'], $dates['to']);
        $this->assertSame('رصيد آخر المدة', $closing['title']);
        $this->assertSame(
            ['المجال', 'رصيد أول المدة', 'حركة الفترة', 'رصيد آخر المدة'],
            $closing['headers'],
        );

        $this->actingAs($admin)
            ->get('/admin/reports/opening-balance/export?from='.$from.'&to='.$to)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_profitability_report_excludes_military_without_view_military_profit(): void
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
        $this->assertStringNotContainsString('عسكري', implode(',', $report['rows'][0]));

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);
        $adminReport = $hub->build('profitability', $dates['from'], $dates['to']);

        $this->assertCount(2, $adminReport['rows']);
    }

    public function test_closing_balance_hides_military_domain_without_view_military_profit(): void
    {
        $ops = $this->userWithRole('operations');
        $from = '2026-06-01';
        $to = '2026-06-30';

        $hub = app(AdminReportsHubService::class);
        $dates = $hub->parseDateRange($from, $to);

        $this->actingAs($ops);
        $report = $hub->build('closing-balance', $dates['from'], $dates['to']);

        $labels = array_column($report['rows'], 0);
        $this->assertContains('الخزنة النقدية', $labels);
        $this->assertContains('مديونية الجهات المدنية', $labels);
        $this->assertNotContains('المستحق السيادي (عسكري)', $labels);
    }
}
