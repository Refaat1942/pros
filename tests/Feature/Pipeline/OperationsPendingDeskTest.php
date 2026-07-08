<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Quote;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * شاشة قرار مكتب التشغيل (الخطوة 8) — موافقة / إرجاع / طباعة العرض.
 */
class OperationsPendingDeskTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_pending_list_shows_discounted_quote_total_for_contract_company(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        app(StockPriceService::class)->addBatch(
            StockItem::first(),
            10,
            200.00,
            $this->makeSupplier(),
            'INV-001',
            now()
        );

        $company = $this->civilianCompany('التأمين الصحي');
        $company->update(['discount_percent' => 10]);

        $patient = $this->civilianPatient($company);
        $case = $this->operationsReadyCase($patient);
        $case->update(['contract_company_id' => $company->id, 'quote_total' => 2000]);

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $quote->update(['total' => 2000]);

        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->getJson('/operations/pending/list')
            ->assertOk()
            ->assertJsonPath('data.0.quote.total', 2000)
            ->assertJsonPath('data.0.quote.display_total', 1800)
            ->assertJsonPath('data.0.display_quote_total', 1800);
    }

    public function test_pending_list_shows_civilian_operations_cases_with_quote(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        app(StockPriceService::class)->addBatch(
            StockItem::first(),
            10,
            200.00,
            $this->makeSupplier(),
            'INV-001',
            now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);

        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);

        $ops = $this->userWithRole('operations');

        $response = $this->actingAs($ops)
            ->getJson('/operations/pending/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $case->id);

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $response->assertJsonPath('data.0.quote.quote_no', $quote->quote_no);
        $response->assertJsonStructure(['data' => [['quote' => ['print_url']]]]);
    }

    public function test_pending_list_includes_tech_notes_when_present(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        app(StockPriceService::class)->addBatch(
            StockItem::first(),
            10,
            200.00,
            $this->makeSupplier(),
            'INV-001',
            now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);

        TechOrderSpec::create([
            'order_ref' => $case->order_ref,
            'case_id' => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'doctor_name' => 'د. اختبار',
            'tech_notes' => 'ملاحظة لمكتب التشغيل',
            'locked' => true,
            'submitted_at' => now()->toDateString(),
        ]);

        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->getJson('/operations/pending/list')
            ->assertOk()
            ->assertJsonPath('data.0.tech_notes', 'ملاحظة لمكتب التشغيل');
    }

    public function test_pending_release_quote_makes_visible_at_reception_and_approves(): void
    {
        $item = $this->stockItem('RM-001', qty: 20, wac: 100.00);
        app(StockPriceService::class)->addBatch(
            $item,
            10,
            200.00,
            $this->makeSupplier(),
            'INV-001',
            now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');
        $recep = $this->userWithRole('reception');

        $this->assertEquals(Quote::STATUS_PENDING, $quote->status);

        $this->actingAs($recep)
            ->getJson('/reception/quote/list')
            ->assertJsonPath('total', 0);

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk()
            ->assertJsonPath('quote.status', Quote::STATUS_ISSUED);

        $case->refresh();
        $item->refresh();

        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertEquals(1, (int) $item->reserved, 'Spec submit reserves stock before operations approval');

        $this->actingAs($recep)
            ->getJson('/reception/quote/list')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.quote_no', $quote->quote_no);
    }

    public function test_pending_approve_reserves_stock_and_moves_to_warehouse(): void
    {
        $item = $this->stockItem('RM-001', qty: 10, wac: 100.00);
        app(StockPriceService::class)->addBatch(
            $item, 10, 200.00, $this->makeSupplier(), 'INV-001', now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');

        // موافقة الجهة (مسح خطاب الموافقة في الاستقبال) قبل إصدار أمر الشغل.
        $this->markEntityApproved($case);

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/approve")
            ->assertOk();

        $case->refresh();
        $item->refresh();

        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_WAREHOUSE, $case->manufacturing_stage);
        $this->assertGreaterThan(0, (int) $item->reserved);
    }

    public function test_pending_return_to_adjustments(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/return", [
                'target' => CaseRecord::STAGE_ADJUSTMENTS,
                'reason' => 'تعديل كميات',
            ])
            ->assertOk();

        $this->assertEquals(CaseRecord::STAGE_ADJUSTMENTS, $case->fresh()->stage_key);
    }

    public function test_civilian_return_blocked_after_quote_released(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        app(StockPriceService::class)->addBatch(
            StockItem::first(),
            10,
            200.00,
            $this->makeSupplier(),
            'INV-001',
            now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk();

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/return", [
                'target' => CaseRecord::STAGE_ADJUSTMENTS,
                'reason' => 'محاولة بعد الإصدار',
            ])
            ->assertStatus(422);
    }

    public function test_pending_return_to_adjustments_shows_rework_reason(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');
        $adjustments = $this->userWithRole('adjustments');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/return", [
                'target' => CaseRecord::STAGE_ADJUSTMENTS,
                'reason' => 'تعديل كميات البنود',
            ])
            ->assertOk();

        $response = $this->actingAs($adjustments)
            ->getJson("/adjustments/adjustments/{$case->id}")
            ->assertOk();

        $response->assertJsonPath('case.rework.reason', 'تعديل كميات البنود');
        $response->assertJsonPath('case.rework.target', CaseRecord::STAGE_ADJUSTMENTS);
    }

    public function test_pending_return_to_technical_unlocks_spec_for_editing(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');
        $specUser = $this->userWithRole('spec');

        TechOrderSpec::create([
            'order_ref' => $case->order_ref,
            'case_id' => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'doctor_name' => 'د. اختبار',
            'tech_notes' => 'توصيف أولي',
            'locked' => true,
            'submitted_at' => now()->toDateString(),
        ]);

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/return", [
                'target' => CaseRecord::STAGE_TECHNICAL,
                'reason' => 'تعديل التوصيف',
            ])
            ->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_TECHNICAL, $case->stage_key);

        $spec = TechOrderSpec::where('case_id', $case->id)->firstOrFail();
        $this->assertFalse($spec->locked);

        $response = $this->actingAs($specUser)
            ->getJson("/spec/spec/{$case->id}")
            ->assertOk();

        $response->assertJsonPath('draft.id', $spec->id);
        $response->assertJsonPath('submitted_spec', null);
        $response->assertJsonPath('case.rework.reason', 'تعديل التوصيف');
        $response->assertJsonPath('case.rework.target', CaseRecord::STAGE_TECHNICAL);

        $this->actingAs($specUser)
            ->putJson("/spec/spec/{$spec->id}", [
                'tech_notes' => 'توصيف معدّل بعد الإرجاع',
                'items' => [
                    ['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 2],
                ],
            ])
            ->assertOk();

        $this->actingAs($specUser)
            ->postJson("/spec/spec/{$spec->id}/submit")
            ->assertOk()
            ->assertJsonPath('case.stage_key', CaseRecord::STAGE_ADJUSTMENTS);
    }

    public function test_operations_can_print_quote(): void
    {
        $this->stockItem('RM-001', qty: 10);
        app(StockPriceService::class)->addBatch(
            StockItem::first(),
            10,
            200.00,
            $this->makeSupplier(),
            'INV-001',
            now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get("/operations/quote/{$quote->id}/print")
            ->assertOk()
            ->assertSee($quote->quote_no, false)
            ->assertSee('عرض سعر', false)
            ->assertSee('وزارة الدفاع', false)
            ->assertSee('مصنع الأجهزة التعويضية', false)
            ->assertSee('المواصفات', false)
            ->assertSee('فقط ', false)
            ->assertSee('<svg', false);
    }

    public function test_pending_page_renders_in_operations_dashboard(): void
    {
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get('/operations/pending')
            ->assertOk()
            ->assertSee('id="pendingTable"', false)
            ->assertSee('مكتب التشغيل — موافقات وعروض الأسعار', false);
    }
}
