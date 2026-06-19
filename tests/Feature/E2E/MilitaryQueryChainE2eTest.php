<?php

namespace Tests\Feature\E2E;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Models\PricingRequest;
use App\Models\Quote;
use App\Services\Dashboard\DashboardQueueService;
use App\Services\StockPriceService;
use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * E2E — مسار عسكري: تجاوز OCR/التسعير + عزل مالي + سجل سيادي.
 */
class MilitaryQueryChainE2eTest extends TestCase
{
    use ProstheticTestHelper;
    use DashboardQueueAssertions;

    private function seedStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 30, wac: 80.00);
        $sup  = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 10, 150.00, $sup, 'INV-MIL-1', now());
    }

    public function test_military_bypass_pipeline_with_sovereign_isolation(): void
    {
        $this->seedStock();
        $company = $this->militaryCompany();
        $rank    = MilitaryRank::create(['name' => 'عقيد', 'rank_code' => 'COL', 'sort_order' => 1, 'is_active' => true]);
        $recep   = $this->userWithRole('reception');
        $doctor  = $this->userWithRole('doctor');
        $spec    = $this->userWithRole('spec');
        $admin   = $this->userWithRole('admin');
        $tech    = $this->userWithRole('technical');
        $ops     = $this->userWithRole('operations');
        $queues  = app(DashboardQueueService::class);

        $patient = $this->registerMilitaryPatientHttp($recep, $company, $rank, 'محمود E2E عسكري');
        $this->assertNotContains($patient->id, $queues->doctorWaitingPatientIds());

        $this->transferPatientToClinicHttp($recep, $patient);
        $this->assertContains($patient->id, $queues->doctorWaitingPatientIds());

        $this->actingAs($doctor);
        $appointmentId = collect($this->getJson('/doctor/queue/list')->json('data'))
            ->firstWhere('patient_id', $patient->id)['id'];

        $this->postJson('/doctor/diagnosis', [
            'patient_id'     => $patient->id,
            'appointment_id' => $appointmentId,
            'diagnosis'      => 'بتر — عسكري E2E',
            'items'          => [['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 1]],
            'lock'           => true,
        ])->assertCreated();

        $case = CaseRecord::where('patient_id', $patient->id)->firstOrFail();
        $this->assertEquals(CaseRecord::PATH_MILITARY, $case->path);

        $this->actingAs($spec);
        $specPage = $this->get("/spec/spec/{$case->id}");
        $specPage->assertOk();
        $this->assertStringNotContainsString('wac', strtolower($specPage->getContent()));

        $specRes = $this->postJson('/spec/spec', [
            'case_id' => $case->id,
            'items'   => [['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 1]],
        ])->assertCreated();

        $this->postJson('/spec/spec/' . $specRes->json('id') . '/submit')->assertOk();

        $pricingId = PricingRequest::where('case_id', $case->id)->value('id');
        $this->assertContains($pricingId, $queues->adminPricingAwaitingIds());
        $this->assertDatabaseMissing('quotes', ['case_id' => $case->id]);

        $this->actingAs($admin);
        $this->postJson("/admin/pricing/{$pricingId}/approve")->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertNotNull($case->work_order_no);
        $this->assertMatchesRegularExpression('/^WO-\d{4}-\d{4}$/', $case->work_order_no);
        $this->assertDatabaseMissing('quotes', ['case_id' => $case->id]);

        $this->actingAs($recep);
        $this->postJson('/reception/ocr/process', [
            'quote_no'        => 'QT-FAKE',
            'patient_name'    => $patient->name,
            'approved_amount' => 100,
            'company_name'    => $company->name,
        ])->assertStatus(422);

        $this->actingAs($tech);
        $bom = Bom::where('case_id', $case->id)->firstOrFail();
        $this->postJson("/technical/bom/{$bom->id}/dispense", ['scanned_barcodes' => ['BC-RM-001']])->assertOk();

        $debtBefore = (float) $company->debt()->first()->due;

        $this->actingAs($ops);
        foreach ([CaseRecord::MFG_GENERATION, CaseRecord::MFG_ASSEMBLY, CaseRecord::MFG_CASTING, CaseRecord::MFG_FINISHING] as $stage) {
            $this->postJson("/operations/operations/{$case->id}/advance", ['manufacturing_stage' => $stage])->assertOk();
        }
        $this->postJson("/operations/operations/{$case->id}/finish-quality")->assertOk();

        $this->actingAs($recep);
        $this->postJson('/reception/delivery/scan', ['scanned_qr' => $patient->patient_qr])->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_DELIVERED, $case->stage_key);
        $this->assertNull($case->invoice_no);

        $company->debt()->first()->refresh();
        $this->assertEquals($debtBefore, (float) $company->debt()->first()->due, 'Military must not post civilian debt');

        $this->assertDatabaseHas('audit_logs', ['tag' => 'financial', 'action' => 'post']);
        $this->assertTrue($queues->patientIsArchived($patient->id));
    }
}
