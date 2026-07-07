<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\Quote;
use App\Models\StockCategory;
use App\Models\TechOrderSpec;
use App\Services\BomService;
use App\Services\StockCatalogService;
use App\Services\StockCategorySchemaService;
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
            ->assertSee('id="btnRefreshCosting"', false);
    }

    public function test_operations_role_cannot_access_costing_dashboard(): void
    {
        $ops = $this->userWithRole('operations');
        // Simulate admin revoking costing-dashboard access
        $ops->role->permissions()->detach(
            \App\Models\Permission::where('dashboard', 'costing')->pluck('id')
        );

        $this->actingAs($ops->fresh())
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

    public function test_costing_list_includes_tech_notes_when_present(): void
    {
        $costing = $this->userWithRole('costing');
        $case = $this->caseInAdjustments();

        TechOrderSpec::create([
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $case->patient->name,
            'company_name' => $case->company_name,
            'doctor_name'  => 'د. اختبار',
            'tech_notes'   => 'ملاحظة للتكاليف',
            'locked'       => true,
            'submitted_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $this->actingAs($costing)
            ->getJson('/costing/queue/list')
            ->assertOk()
            ->assertJsonPath('data.0.tech_notes', 'ملاحظة للتكاليف');
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
        $this->assertEquals(300.00, (float) $response->json('pricing.internal_total'));
        $this->assertEquals(400.00, (float) $response->json('pricing.computed_total'));
        $this->assertEquals(400.00, (float) $response->json('pricing.overhead_breakdown.gross_before_discount'));
        $response->assertJsonMissingPath('pricing.items.0.wac_unit');
        $response->assertJsonStructure([
            'pricing' => [
                'items' => [['stock_item_code', 'name', 'qty', 'criteria', 'line_total']],
            ],
        ]);
    }

    public function test_costing_show_displays_dynamic_category_criteria(): void
    {
        $schema = app(StockCategorySchemaService::class);
        $category = StockCategory::create(['name' => 'مفاصل صناعية']);
        $schema->syncFields($category, [
            ['label' => 'عدد القطع', 'type' => 'number', 'field_key' => 'pieces', 'required' => true],
            ['label' => 'النوع', 'type' => 'text', 'field_key' => 'joint_type', 'required' => false],
        ]);

        $stock = app(StockCatalogService::class)->create([
            'name'        => 'مفصل Carbon',
            'code'        => 'RM-JOINT',
            'qty'         => 10,
            'price'       => 500,
            'category_id' => $category->id,
            'attributes'  => ['pieces' => 3, 'joint_type' => 'Spring'],
        ]);

        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($stock, 10, 500.00, $supplier, 'INV-JOINT', now());

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-JOINT', 'qty' => 1],
        ]);

        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $response = $this->actingAs($this->userWithRole('costing'))
            ->getJson("/costing/queue/{$case->id}")
            ->assertOk();

        $criteria = (string) $response->json('pricing.items.0.criteria');
        $this->assertStringContainsString('عدد القطع: 3', $criteria);
        $this->assertStringContainsString('النوع: Spring', $criteria);
    }

    public function test_costing_overhead_includes_same_code_from_spec_and_adjustments(): void
    {
        $item = $this->stockItem('ITM-001', qty: 20, wac: 1000.00);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 2000.00, $supplier, 'INV-ITM-001', now());

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'ITM-001', 'qty' => 1],
        ]);

        $adjustments = $this->userWithRole('adjustments');

        $this->actingAs($adjustments)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    ['stock_item_code' => 'ITM-001', 'name' => 'ركبه هيدروليكيه', 'qty' => 1],
                ],
            ])
            ->assertCreated();

        $this->actingAs($adjustments)
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $response = $this->actingAs($this->userWithRole('costing'))
            ->getJson("/costing/queue/{$case->id}")
            ->assertOk();

        // بند توصيف + بند معدلات لنفس الصنف → النسب على إجمالي المواد (4000) وليس WAC فقط
        $this->assertCount(2, $response->json('pricing.items'));
        $this->assertEquals(4000.00, (float) $response->json('pricing.materials_highest_total'));
        $this->assertEquals(3000.00, (float) $response->json('pricing.internal_total'));
        $this->assertEquals(4000.00, (float) $response->json('pricing.overhead_breakdown.materials_total'));

        $overheads = collect($response->json('pricing.overhead_breakdown.overheads'));
        $this->assertEquals(1200.00, (float) $overheads->first()['amount']);
        $this->assertEquals(4000.00, round($overheads->sum('amount'), 2));
        $this->assertEquals(4000.00, (float) $response->json('pricing.overhead_breakdown.gross_before_discount'));
        $this->assertEquals(4000.00, (float) $response->json('pricing.computed_total'));
    }

    public function test_costing_total_includes_adjustment_bom_items(): void
    {
        $case = $this->caseInAdjustments();

        $item2 = $this->stockItem('RM-002', qty: 10);
        app(StockPriceService::class)->addBatch(
            $item2,
            10,
            1000.00,
            \App\Models\Supplier::query()->firstOrFail(),
            'INV-002',
            now(),
        );

        $adjustments = $this->userWithRole('adjustments');

        $this->actingAs($adjustments)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    ['stock_item_code' => 'RM-002', 'name' => 'مكوّن إضافي', 'qty' => 1],
                ],
            ])
            ->assertCreated();

        // طلب تسعير قديم ببنود التوصيف فقط — كان سبب ظهور إجمالي ناقص
        $stalePricing = PricingRequest::create([
            'request_no'   => '111111',
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $case->patient->name,
            'company_name' => $case->company_name,
            'request_date' => now()->toDateString(),
            'items_count'  => 1,
            'patient_type' => $case->patient_type,
            'status_key'   => 'processing',
            'step'         => PricingRequest::STEP_ADMIN,
        ]);
        \App\Models\PricingRequestItem::create([
            'pricing_request_id' => $stalePricing->id,
            'stock_item_code'    => 'RM-001',
            'name'               => 'RM-001',
            'qty'                => 2,
            'unit_price'         => 200,
            'line_total'         => 400,
        ]);
        $stalePricing->update(['computed_total' => 400, 'internal_total' => 400]);
        $case->update(['pricing_request_id' => $stalePricing->id]);

        $this->actingAs($adjustments)
            ->postJson("/adjustments/adjustments/{$case->id}/complete");

        $response = $this->actingAs($this->userWithRole('costing'))
            ->getJson("/costing/queue/{$case->id}")
            ->assertOk();

        // RM-001 ×2 @200 + RM-002 ×1 @1000 → materials 1400
        $this->assertEquals(1400.00, (float) $response->json('pricing.computed_total'));
        $this->assertEquals(850.00, (float) $response->json('pricing.internal_total'));
        $this->assertCount(2, $response->json('pricing.items'));
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
