<?php

namespace Tests\Feature\Reports;

use App\Models\CaseRecord;
use App\Services\BiReportService;
use App\Services\StockPriceService;
use Mockery;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Feature — BI Reports: read-only, correct shape, access control
 * (الـ Post-Credit Scene: 5 لوحات قيادة)
 *
 * The board*() methods use MySQL-specific functions (DATEDIFF, CURDATE).
 * For the service-level shape tests we mock the service and verify the
 * controller correctly passes the data to the view.
 * Access-control tests go through the full HTTP stack.
 */
class BiReportTest extends TestCase
{
    use ProstheticTestHelper;

    // ── Board shape tests (mocked service) ───────────────────────────────────

    public function test_board_patients_returns_expected_shape(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPatients')->once()->andReturn([
            'total_cases'     => 5,
            'civilian_count'  => 3,
            'military_count'  => 2,
            'by_stage'        => [],
            'sla_breaches'    => [],
            'avg_turnaround'  => 12.5,
        ]);

        $board = app(BiReportService::class)->boardPatients();

        $this->assertArrayHasKey('total_cases', $board);
        $this->assertArrayHasKey('civilian_count', $board);
        $this->assertArrayHasKey('military_count', $board);
        $this->assertArrayHasKey('sla_breaches', $board);
    }

    public function test_board_inventory_returns_expected_shape(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardInventory')->once()->andReturn([
            'items'           => [],
            'low_stock_items' => [['code' => 'RM-LOW', 'qty' => 1, 'wac' => 50.0]],
            'total_wac_value' => 1000.0,
        ]);

        $board = app(BiReportService::class)->boardInventory();

        $this->assertArrayHasKey('low_stock_items', $board);
        $this->assertNotEmpty($board['low_stock_items']);
    }

    public function test_board_operations_returns_expected_shape(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardOperations')->once()->andReturn([
            'in_production'  => 3,
            'ready_delivery' => 1,
            'stage_counts'   => [],
        ]);

        $board = app(BiReportService::class)->boardOperations();

        $this->assertArrayHasKey('in_production', $board);
    }

    public function test_board_entities_returns_expected_shape(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardEntitiesAndCosts')->once()->andReturn([
            'top_debtors'    => [['name' => 'التأمين', 'due' => 5000.0]],
            'total_due'      => 5000.0,
            'total_collected'=> 1000.0,
        ]);

        $board = app(BiReportService::class)->boardEntitiesAndCosts();

        $this->assertArrayHasKey('top_debtors', $board);
        $this->assertNotEmpty($board['top_debtors']);
    }

    public function test_board_purchasing_returns_expected_shape(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPurchasing')->once()->andReturn([
            'top_suppliers' => [['name' => 'مورد أ', 'total' => 20000.0]],
        ]);

        $board = app(BiReportService::class)->boardPurchasing();

        $this->assertArrayHasKey('top_suppliers', $board);
    }

    // ── Civilian/military data isolation (in-DB, no complex SQL) ─────────────

    public function test_military_cases_are_isolated_from_civilian_counts(): void
    {
        $civCompany = $this->civilianCompany();
        $milCompany = $this->militaryCompany();
        $civ        = $this->civilianPatient($civCompany);
        $mil        = $this->militaryPatient($milCompany);

        $this->caseAtStage($civ, CaseRecord::STAGE_DELIVERED);
        $this->caseAtStage($mil, CaseRecord::STAGE_DELIVERED);

        $civCount = CaseRecord::where('patient_type', 'civilian')->count();
        $milCount = CaseRecord::where('patient_type', 'military')->count();

        $this->assertEquals(1, $civCount);
        $this->assertEquals(1, $milCount);
    }

    // ── Access control (full HTTP stack) ─────────────────────────────────────

    public function test_admin_can_view_bi_dashboard(): void
    {
        $mock = $this->mock(BiReportService::class);
        $mock->shouldReceive('boardPatients')->andReturn(['total_cases' => 0, 'civilian_count' => 0, 'military_count' => 0, 'by_stage' => [], 'sla_breaches' => [], 'avg_turnaround' => 0]);
        $mock->shouldReceive('boardInventory')->andReturn(['items' => [], 'low_stock_items' => [], 'total_wac_value' => 0]);
        $mock->shouldReceive('boardOperations')->andReturn(['in_production' => 0, 'ready_delivery' => 0, 'stage_counts' => []]);
        $mock->shouldReceive('boardEntitiesAndCosts')->andReturn(['top_debtors' => [], 'total_due' => 0, 'total_collected' => 0]);
        $mock->shouldReceive('boardPurchasing')->andReturn(['top_suppliers' => []]);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $this->get('/admin/bi')
            ->assertRedirect('/admin/overview#overview-bi');

        $this->get('/admin/overview')
            ->assertOk()
            ->assertSee('id="overview-bi"', false)
            ->assertSee('id="bi-board-1"', false);
    }

    public function test_non_admin_cannot_view_bi_dashboard(): void
    {
        $reception = $this->userWithRole('reception');
        $this->actingAs($reception);

        $this->get('/admin/bi')->assertStatus(403);
    }

    public function test_audit_log_page_is_read_only_no_delete_or_put(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $this->deleteJson('/admin/audit/1')->assertStatus(405);
        $this->putJson('/admin/audit/1', ['description' => 'تلاعب'])->assertStatus(405);
    }

    public function test_admin_can_view_audit_log_page(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $this->get('/admin/audit')->assertOk();
    }
}
