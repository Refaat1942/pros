<?php

namespace Tests\Feature\Pipeline;

use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * المعدلات (الخطوة 4) — طابور المراجعة والإضافة قبل التكاليف.
 *
 * يعرض الطابور الحالات في مرحلة STAGE_ADJUSTMENTS فقط (بعد إرسال التوصيف مباشرةً).
 * بنود الفني (source=spec) للقراءة فقط، ويمكن إضافة بنود مستشار (source=adjustment).
 */
class AdjustmentsListTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_adjustments_page_includes_dynamic_table_controls(): void
    {
        $user = $this->userWithRole('adjustments');

        $this->actingAs($user)
            ->get('/adjustments/adjustments')
            ->assertOk()
            ->assertSee('id="btnRefreshAdj"', false)
            ->assertSee('id="adjustmentsTable"', false);
    }

    public function test_adjustments_list_excludes_cases_outside_adjustments_stage(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');

        $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $this->actingAs($user)
            ->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_civilian_case_appears_in_adjustments_queue(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($user)
            ->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.pathway_label', 'مدني')
            ->assertJsonPath('data.0.patient.name', $patient->name);
    }

    public function test_military_case_appears_in_adjustments_queue(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $user    = $this->userWithRole('adjustments');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($user)
            ->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.pathway_label', 'عسكري');
    }

    public function test_ready_delivery_case_is_excluded_from_adjustments_list(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');

        $this->caseAtStage($patient, CaseRecord::STAGE_READY_DELIVERY);

        $this->actingAs($user)
            ->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_consultant_can_append_items_while_spec_items_stay_read_only(): void
    {
        $this->seedStockWithPriceBatch();
        $this->stockItem('RM-002', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    ['stock_item_code' => 'RM-002', 'name' => 'مكوّن مستشار', 'qty' => 2],
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('bom_items', [
            'bom_id'          => $bom->id,
            'stock_item_code' => 'RM-001',
            'source'          => BomItem::SOURCE_SPEC,
        ]);
        $this->assertDatabaseHas('bom_items', [
            'bom_id'          => $bom->id,
            'stock_item_code' => 'RM-002',
            'source'          => BomItem::SOURCE_ADJUSTMENT,
        ]);
    }

    public function test_adding_unknown_stock_item_returns_clear_error(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('adjustments');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    ['stock_item_code' => '52132', 'name' => 'كفته', 'qty' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'الصنف غير موجود: 52132');
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
