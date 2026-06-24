<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\Quote;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * لوحة التكاليف المستقلة (دور costing) — توقف عند cost_calc.
 */
class CostingDashboardTest extends TestCase
{
    use ProstheticTestHelper;

    private function seedStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }

    private function caseInAdjustments(): CaseRecord
    {
        $this->seedStock();
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);

        return $case;
    }

    public function test_adjustments_complete_stops_at_cost_calc(): void
    {
        $user = $this->userWithRole('adjustments');
        $case = $this->caseInAdjustments();

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/complete")
            ->assertOk()
            ->assertJsonPath('case.stage_key', CaseRecord::STAGE_COST_CALC);

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_COST_CALC, $case->stage_key);
        $this->assertDatabaseMissing('quotes', ['case_id' => $case->id]);
    }

    public function test_costing_role_can_access_separate_dashboard(): void
    {
        $costing = $this->userWithRole('costing');

        $this->actingAs($costing)
            ->get('/costing/costing')
            ->assertOk()
            ->assertSee('id="costingTable"', false)
            ->assertSee('تأكيد عرض سعر', false);
    }

    public function test_operations_role_cannot_access_costing_dashboard(): void
    {
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get('/costing/costing')
            ->assertForbidden();
    }

    public function test_costing_list_shows_pending_cases(): void
    {
        $costing = $this->userWithRole('costing');
        $case = $this->caseInAdjustments();

        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $this->actingAs($costing)
            ->getJson('/costing/queue/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.stage_key', CaseRecord::STAGE_COST_CALC);
    }

    public function test_costing_show_includes_wac_for_view_costs_role(): void
    {
        $costing = $this->userWithRole('costing');
        $case = $this->caseInAdjustments();

        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $response = $this->actingAs($costing)
            ->getJson("/costing/queue/{$case->id}")
            ->assertOk();

        $response->assertJsonPath('can_see_internal', true);
        $this->assertEquals(400.00, (float) $response->json('pricing.computed_total'));
    }

    public function test_costing_confirm_issues_quote_and_moves_to_operations(): void
    {
        $costing = $this->userWithRole('costing');
        $case = $this->caseInAdjustments();

        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $this->actingAs($costing)
            ->postJson("/costing/queue/{$case->id}/confirm")
            ->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertNotNull(Quote::where('case_id', $case->id)->first());
    }

    public function test_costing_confirm_after_operations_rework_refreshes_existing_quote(): void
    {
        $adjustments = $this->userWithRole('adjustments');
        $costing = $this->userWithRole('costing');
        $case = $this->caseInAdjustments();

        $this->actingAs($adjustments)
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $this->actingAs($costing)
            ->postJson("/costing/queue/{$case->id}/confirm")
            ->assertOk();

        $firstQuote = Quote::where('case_id', $case->id)->firstOrFail();
        $firstQuoteNo = $firstQuote->quote_no;

        app(\App\Services\OperationsService::class)->returnForRework(
            $case->fresh(),
            CaseRecord::STAGE_ADJUSTMENTS,
            'تعديل بنود',
        );

        $this->actingAs($adjustments)
            ->postJson("/adjustments/adjustments/{$case->id}/complete")
            ->assertOk();

        $this->actingAs($costing)
            ->postJson("/costing/queue/{$case->id}/confirm")
            ->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertEquals(1, Quote::where('case_id', $case->id)->count());
        $this->assertEquals($firstQuoteNo, Quote::where('case_id', $case->id)->value('quote_no'));
    }

    public function test_military_costing_confirm_auto_approves_to_warehouse(): void
    {
        $this->seedStock();
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $this->actingAs($this->userWithRole('costing'))
            ->postJson("/costing/queue/{$case->id}/confirm")
            ->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertDatabaseMissing('quotes', ['case_id' => $case->id]);
    }
}
