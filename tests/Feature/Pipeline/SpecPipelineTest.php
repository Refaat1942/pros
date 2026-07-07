<?php

namespace Tests\Feature\Pipeline;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\StockItem;
use App\Services\MedicalRecordService;
use App\Services\SpecService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SpecPipelineTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_spec_submit_creates_raw_bom_and_advances_case_to_adjustments(): void
    {
        $this->stockItem('RM-001', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $doctor = $this->userWithRole('doctor');
        $specUser = $this->userWithRole('spec');

        $this->actingAs($doctor);

        $record = MedicalRecord::create([
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'national_id' => $patient->national_id,
            'company_name' => $patient->company_name,
            'patient_type' => $patient->patient_type,
            'diagnosis' => 'بتر فوق الركبة',
            'prescription' => 'ركبة ذكية',
            'doctor_name' => $doctor->name,
            'doctor_user_id' => $doctor->id,
            'record_date' => now()->toDateString(),
            'status' => MedicalRecord::STATUS_DRAFT,
            'locked' => false,
        ]);

        app(MedicalRecordService::class)->lock($record);

        $case = CaseRecord::where('patient_id', $patient->id)->first();
        $this->assertEquals(CaseRecord::STAGE_TECHNICAL, $case->stage_key);

        $this->actingAs($specUser);

        $draft = app(SpecService::class)->saveDraft([
            'case_id' => $case->id,
            'tech_notes' => 'توصيف اختبار',
            'items' => [
                ['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 2],
            ],
        ]);

        // الإرسال الآن يُحوّل التوصيف إلى المعدلات (لا تسعير هنا — عمى مالي للفني).
        $updatedCase = app(SpecService::class)->submit($draft);

        $this->assertEquals(CaseRecord::STAGE_ADJUSTMENTS, $updatedCase->stage_key);

        // لا يُنشأ طلب تسعير في مرحلة التوصيف/المعدلات قبل إغلاق المعدلات.
        $this->assertDatabaseMissing('pricing_requests', ['case_id' => $case->id]);

        $bom = Bom::where('case_id', $case->id)->first();
        $this->assertNotNull($bom);
        $this->assertEquals(Bom::STAGE_RAW, $bom->stage);
        $this->assertEquals(1, $bom->items()->count());

        $stock = StockItem::where('code', 'RM-001')->first();
        $this->assertEquals(2, $stock->reserved, 'Spec submit must reserve items (backorder allowed)');
    }

    public function test_spec_api_create_endpoint_returns_catalog_without_qty(): void
    {
        $this->stockItem('RM-002', qty: 15);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $specUser = $this->userWithRole('spec');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_TECHNICAL);

        $response = $this->actingAs($specUser)
            ->getJson("/spec/spec/{$case->id}");

        $response->assertOk();
        $catalog = $response->json('stock_catalog');
        $this->assertNotEmpty($catalog);
        $this->assertArrayNotHasKey('qty', $catalog[0]);
        $this->assertArrayNotHasKey('wac', $catalog[0]);
        $this->assertArrayNotHasKey('reserved', $catalog[0]);
    }
}
