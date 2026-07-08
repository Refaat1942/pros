<?php

namespace Tests\Feature\E2E;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Models\Quote;
use App\Services\Dashboard\DashboardQueueService;
use App\Services\StockPriceService;
use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * E2E — مسار مدني كامل مع Query-Chain Monitoring عبر 7 لوحات.
 */
class CivilianQueryChainE2eTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    private function seedStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 30, wac: 80.00);
        $sup = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 10, 150.00, $sup, 'INV-E2E-1', now());
        app(StockPriceService::class)->addBatch($item, 10, 200.00, $sup, 'INV-E2E-2', now());
    }

    public function test_civilian_full_pipeline_query_chain_and_blade_flow(): void
    {
        $this->seedStock();
        $company = $this->civilianCompany();
        $recep = $this->userWithRole('reception');
        $doctor = $this->userWithRole('doctor');
        $spec = $this->userWithRole('spec');
        $adj = $this->userWithRole('adjustments');
        $admin = $this->userWithRole('admin');
        $tech = $this->userWithRole('technical');
        $ops = $this->userWithRole('operations');
        $queues = app(DashboardQueueService::class);

        // ── Step 1: Reception — register + QR + doctor queue ─────────────────
        $patient = $this->registerCivilianPatientHttp($recep, $company, 'خالد E2E مدني');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $patient->patient_code);
        $this->assertMatchesRegularExpression('/^QR-\d{6}$/', $patient->patient_qr);
        $this->assertNotContains($patient->id, $queues->doctorWaitingPatientIds());

        $this->transferPatientToClinicHttp($recep, $patient);
        $this->assertContains($patient->id, $queues->doctorWaitingPatientIds());

        $appointmentsPage = $this->actingAs($recep)->get('/reception/appointments');
        $appointmentsPage->assertOk();

        $card = $this->actingAs($recep)->getJson("/reception/patients/{$patient->id}");
        $card->assertOk()
            ->assertJsonPath('patient_qr', $patient->patient_qr)
            ->assertJsonStructure(['queue_number']);

        // ── Step 2: Doctor — approve exam, leave queue, enter spec ─────────
        $this->actingAs($doctor);
        $queue = $this->getJson('/doctor/queue/list');
        $queue->assertOk();
        $queueIds = collect($queue->json('data'))->pluck('patient_id')->all();
        $this->assertContains($patient->id, $queueIds);

        $appointmentId = collect($queue->json('data'))->firstWhere('patient_id', $patient->id)['id'];

        $diagPage = $this->get('/doctor/queue?appointment='.$appointmentId);
        $diagPage->assertOk();
        $this->assertStringNotContainsString('wac', strtolower($diagPage->getContent()));
        $this->assertStringNotContainsString('quote_total', strtolower($diagPage->getContent()));

        $lock = $this->postJson('/doctor/diagnosis', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointmentId,
            'diagnosis' => 'بتر طرف سفلي — E2E',
            'items' => [['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 2]],
            'lock' => true,
        ]);
        $lock->assertCreated();

        $this->assertNotContains($patient->id, $queues->doctorWaitingPatientIds());

        $case = CaseRecord::where('patient_id', $patient->id)->firstOrFail();
        $this->assertEquals(CaseRecord::STAGE_TECHNICAL, $case->stage_key);
        $this->assertContains($case->id, $queues->specTechnicalCaseIds());

        // ── Step 3: Spec — submit raw BOM → adjustments (no pricing yet) ─────
        $this->actingAs($spec);
        $specPage = $this->get('/spec/orders');
        $specPage->assertOk();
        $specPage->assertSee('عمى مالي ومخزني', false);

        $specDetail = $this->getJson("/spec/spec/{$case->id}");
        $specDetail->assertOk();

        $create = $this->postJson('/spec/spec', [
            'case_id' => $case->id,
            'items' => [['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 2]],
        ]);
        $create->assertCreated();
        $specId = $create->json('id');

        $submit = $this->postJson("/spec/spec/{$specId}/submit");
        $submit->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_ADJUSTMENTS, $case->stage_key);
        $this->assertNotContains($case->id, $queues->specTechnicalCaseIds());
        $this->assertContains($case->id, $queues->adjustmentsCaseIds());
        $this->assertDatabaseMissing('pricing_requests', ['case_id' => $case->id]);

        // ── Step 4: Adjustments — review (read-only) + complete → cost_calc (STOP) ─
        $this->actingAs($adj);
        $this->getJson('/adjustments/adjustments/list')->assertOk();

        $this->postJson("/adjustments/adjustments/{$case->id}/complete")->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_COST_CALC, $case->stage_key);

        $pricingId = PricingRequest::where('case_id', $case->id)->value('id');
        $this->assertNotNull($pricingId);
        $this->assertDatabaseMissing('quotes', ['case_id' => $case->id]);

        // ── Step 4b: Costing — separate dashboard, read-only + confirm ───────
        $costing = $this->userWithRole('costing');
        $this->actingAs($costing);
        $this->get('/costing/costing')->assertOk();

        $costDetail = $this->getJson("/costing/queue/{$case->id}");
        $costDetail->assertOk();
        $this->assertEquals(400.00, (float) $costDetail->json('pricing.computed_total'));

        $this->postJson("/costing/queue/{$case->id}/confirm")->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);

        $this->assertContains($case->id, $queues->operationsPendingCaseIds());

        // تسعير تلقائي (طرف صناعي): مواد 400 + مكوّنات 400 = 800 × 1.95 = 1560.
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $this->assertEquals(1560.00, (float) $quote->total);
        $this->assertEquals(Quote::STATUS_PENDING, $quote->status);

        // ── Step 5: مكتب التشغيل — إصدار عرض السعر للاستقبال ───────────────
        $this->actingAs($ops);
        $this->postJson("/operations/pending/{$case->id}/release-quote")->assertOk();
        $quote->refresh();
        $case->refresh();
        $this->assertEquals(Quote::STATUS_ISSUED, $quote->status);
        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);

        // ── Step 5c: Reception prints quote + processes OCR approval letter ─
        // (يُسجّل موافقة الجهة فقط — لا يُصدر أمر الشغل)
        $this->actingAs($ops);

        $printOps = $this->get("/operations/quote/{$quote->id}/print");
        $printOps->assertOk();
        $printOps->assertSee($quote->quote_no, false);

        $this->actingAs($recep);

        $print = $this->get("/reception/quote/{$quote->id}/print");
        $print->assertOk();
        $print->assertSee($quote->quote_no, false);

        $ocrBad = $this->postJson('/reception/ocr/process', [
            'quote_no' => $quote->quote_no,
            'patient_name' => $patient->name,
            'approved_amount' => 999.00,
            'company_name' => $company->name,
        ]);
        $ocrBad->assertStatus(422)->assertJsonPath('ocr', true);

        $ocrOk = $this->postJson('/reception/ocr/process', [
            'quote_no' => $quote->quote_no,
            'patient_name' => $patient->name,
            'approved_amount' => 1560.00,
            'company_name' => $company->name,
            'letter_ref' => 'LTR-E2E-001',
        ]);
        $ocrOk->assertOk();

        // الموافقة مُسجّلة، الحالة ما زالت في مكتب التشغيل بلا أمر شغل.
        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertNull($case->work_order_no);
        $this->assertEquals(Quote::STATUS_APPROVED, $quote->fresh()->status);

        // ── Step 5d: Operations issues the work order → manufacturing ───────
        $this->actingAs($ops);
        $approve = $this->postJson("/operations/pending/{$case->id}/approve");
        $approve->assertOk();

        $case->refresh();
        $this->assertMatchesRegularExpression('/^WO-\d{4}-\d{4}$/', (string) $case->work_order_no);
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertNotContains($case->id, $queues->operationsManufacturingCaseIds());

        // ── Step 6: Warehouse — barcode dispense + debt ──────────────────────
        $this->actingAs($tech);
        $bom = Bom::where('case_id', $case->id)->firstOrFail();
        $this->assertContains($bom->id, $queues->technicalBomRawIds());

        $mismatch = $this->postJson("/technical/bom/{$bom->id}/dispense", [
            'scanned_barcodes' => ['BC-RM-999', 'BC-RM-999'],
        ]);
        $mismatch->assertStatus(422)->assertJsonPath('blocked', true);

        // qty=2 يتطلب مسحتين لنفس الباركود (مطابقة كود + صنف + كمية).
        $dispense = $this->postJson("/technical/bom/{$bom->id}/dispense", [
            'scanned_barcodes' => ['BC-RM-001', 'BC-RM-001'],
        ]);
        $dispense->assertOk();
        $this->assertEquals(Bom::STAGE_WIP, $bom->fresh()->stage);
        $this->assertContains($case->id, $queues->operationsManufacturingCaseIds());

        // ── Step 7: Workshop — sub-stages + quality finish ───────────────────
        $workshop = $this->userWithRole('workshop');
        $this->actingAs($workshop);
        foreach ([
            CaseRecord::MFG_GENERATION,
            CaseRecord::MFG_ASSEMBLY,
            CaseRecord::MFG_CASTING,
            CaseRecord::MFG_FINISHING,
        ] as $stage) {
            $this->postJson("/workshop/workshop/{$case->id}/advance", [
                'manufacturing_stage' => $stage,
            ])->assertOk();
        }

        $finish = $this->postJson("/workshop/workshop/{$case->id}/finish-quality");
        $finish->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
        $this->assertContains($case->id, $queues->receptionDeliveryReadyCaseIds());

        // ── Step 8: Reception final delivery — closed + invoice + archive ─
        $this->actingAs($this->userWithRole('reception'));
        $close = $this->postJson('/reception/delivery/scan', ['scanned_qr' => $patient->patient_qr]);
        $close->assertOk()->assertJsonPath('closed', true);

        $case->refresh();
        $patient->refresh();
        $this->assertEquals(CaseRecord::STAGE_DELIVERED, $case->stage_key);
        $this->assertNotNull($case->invoice_no);
        $this->assertEquals(1560.00, (float) $case->invoice_total);
        $this->assertTrue($queues->patientIsArchived($patient->id));
        $this->assertNotContains($case->id, $queues->receptionDeliveryReadyCaseIds());
        $this->assertContains($case->id, $queues->deliveredCaseIds());
    }
}
