<?php

namespace Tests\Feature\Pipeline;

use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
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
        $user = $this->userWithRole('adjustments');

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
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

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

    public function test_adjustments_list_includes_tech_notes_when_present(): void
    {
        $this->seedStockWithPriceBatch();

        $patient = $this->civilianPatient($this->civilianCompany());
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        TechOrderSpec::create([
            'order_ref' => $case->order_ref,
            'case_id' => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'doctor_name' => 'د. اختبار',
            'tech_notes' => 'ملاحظة فنية من التوصيف',
            'locked' => true,
            'submitted_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('data.0.tech_notes', 'ملاحظة فنية من التوصيف');
    }

    public function test_adjustments_list_omits_blank_tech_notes(): void
    {
        $this->seedStockWithPriceBatch();

        $patient = $this->civilianPatient($this->civilianCompany());
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        TechOrderSpec::create([
            'order_ref' => $case->order_ref,
            'case_id' => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'doctor_name' => 'د. اختبار',
            'tech_notes' => '   ',
            'locked' => true,
            'submitted_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson('/adjustments/adjustments/list')
            ->assertOk()
            ->assertJsonPath('data.0.tech_notes', null);
    }

    public function test_military_case_appears_in_adjustments_queue(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->militaryCompany();
        $patient = $this->militaryPatient($company);
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

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
        $user = $this->userWithRole('adjustments');

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
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

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
            'bom_id' => $bom->id,
            'stock_item_code' => 'RM-001',
            'source' => BomItem::SOURCE_SPEC,
        ]);
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bom->id,
            'stock_item_code' => 'RM-002',
            'source' => BomItem::SOURCE_ADJUSTMENT,
        ]);
    }

    public function test_consultant_can_remove_adjustment_items_but_not_spec_items(): void
    {
        $this->seedStockWithPriceBatch();
        $this->stockItem('RM-002', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

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

        $specItem = BomItem::where('bom_id', $bom->id)
            ->where('source', BomItem::SOURCE_SPEC)
            ->firstOrFail();
        $adjItem = BomItem::where('bom_id', $bom->id)
            ->where('source', BomItem::SOURCE_ADJUSTMENT)
            ->firstOrFail();

        $this->actingAs($user)
            ->deleteJson("/adjustments/adjustments/{$case->id}/items/{$specItem->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن حذف بنود التوصيف الفني — للقراءة فقط.');

        $this->actingAs($user)
            ->deleteJson("/adjustments/adjustments/{$case->id}/items/{$adjItem->id}")
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف البند من قائمة المعدلات.');

        $this->assertDatabaseHas('bom_items', [
            'id' => $specItem->id,
            'stock_item_code' => 'RM-001',
            'source' => BomItem::SOURCE_SPEC,
        ]);
        $this->assertDatabaseMissing('bom_items', ['id' => $adjItem->id]);
    }

    public function test_consultant_can_edit_adjustment_item_qty_but_not_spec_qty(): void
    {
        $this->seedStockWithPriceBatch();
        $this->stockItem('RM-002', qty: 10);

        $patient = $this->civilianPatient($this->civilianCompany());
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

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

        $specItem = BomItem::where('bom_id', $bom->id)->where('source', BomItem::SOURCE_SPEC)->firstOrFail();
        $adjItem = BomItem::where('bom_id', $bom->id)->where('source', BomItem::SOURCE_ADJUSTMENT)->firstOrFail();

        // تعديل كمية بند التوصيف مرفوض.
        $this->actingAs($user)
            ->patchJson("/adjustments/adjustments/{$case->id}/items/{$specItem->id}", ['qty' => 4])
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن تعديل بنود التوصيف الفني — للقراءة فقط.');

        // تعديل كمية بند المعدلات مقبول.
        $this->actingAs($user)
            ->patchJson("/adjustments/adjustments/{$case->id}/items/{$adjItem->id}", ['qty' => 5])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث كمية البند.');

        $this->assertSame(5, (int) $adjItem->fresh()->qty);
    }

    public function test_editing_adjustment_qty_above_available_allows_backorder(): void
    {
        $this->seedStockWithPriceBatch();
        $this->stockItem('RM-002', qty: 3);

        $patient = $this->civilianPatient($this->civilianCompany());
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [['stock_item_code' => 'RM-002', 'name' => 'مكوّن', 'qty' => 2]],
            ])
            ->assertCreated();

        $adjItem = BomItem::where('stock_item_code', 'RM-002')->where('source', BomItem::SOURCE_ADJUSTMENT)->firstOrFail();

        // يُسمح بتجاوز المتاح — الزيادة إلى 10 تُسجَّل كـ backorder (متاح سالب).
        $this->actingAs($user)
            ->patchJson("/adjustments/adjustments/{$case->id}/items/{$adjItem->id}", ['qty' => 10])
            ->assertOk();

        $this->assertSame(10, (int) $adjItem->fresh()->qty);

        // RM-002: رصيد 3، محجوز 10 ⇒ متاح = -7 (backorder).
        $stock = StockItem::where('code', 'RM-002')->firstOrFail();
        $this->assertSame(-7, $stock->availableQty());
    }

    public function test_warehouse_bom_api_merges_spec_and_adjustment_lines_with_same_code(): void
    {
        $this->seedStockWithPriceBatch();
        $this->stockItem('RM-002', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
            ['stock_item_code' => 'RM-002', 'qty' => 1],
        ]);

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    ['stock_item_code' => 'RM-002', 'name' => 'مفصل كوع', 'qty' => 1],
                ],
            ])
            ->assertCreated();

        $technical = $this->userWithRole('technical');

        $this->actingAs($technical)
            ->getJson("/technical/bom/{$bom->id}")
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.1.stock_item_code', 'RM-002')
            ->assertJsonPath('items.1.qty', 2);
    }

    public function test_adjustment_adding_same_code_twice_merges_into_one_adjustment_row(): void
    {
        $this->seedStockWithPriceBatch();
        $this->stockItem('RM-002', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    ['stock_item_code' => 'RM-002', 'name' => 'مكوّن مستشار', 'qty' => 1],
                ],
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    ['stock_item_code' => 'RM-002', 'name' => 'مكوّن مستشار', 'qty' => 2],
                ],
            ])
            ->assertCreated();

        $this->assertSame(
            1,
            BomItem::where('bom_id', $bom->id)
                ->where('stock_item_code', 'RM-002')
                ->where('source', BomItem::SOURCE_ADJUSTMENT)
                ->count()
        );

        $this->assertSame(
            3,
            (int) BomItem::where('bom_id', $bom->id)
                ->where('stock_item_code', 'RM-002')
                ->where('source', BomItem::SOURCE_ADJUSTMENT)
                ->value('qty')
        );
    }

    public function test_adding_unknown_stock_item_returns_clear_error(): void
    {
        $this->seedStockWithPriceBatch();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

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
            ->assertJsonPath('message', 'الصنف المختار غير موجود في المخزون.');
    }

    public function test_adding_item_with_qty_above_available_allows_backorder(): void
    {
        $this->seedStockWithPriceBatch();
        $this->stockItem('RM-002', qty: 2);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        // يُسمح بطلب كمية أكبر من المتاح — يُسجَّل رصيد سالب (backorder).
        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    ['stock_item_code' => 'RM-002', 'name' => 'مكوّن مستشار', 'qty' => 5],
                ],
            ])
            ->assertCreated();

        // RM-002: رصيد 2، محجوز 5 ⇒ متاح = -3.
        $stock = StockItem::where('code', 'RM-002')->firstOrFail();
        $this->assertSame(-3, $stock->availableQty());
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
