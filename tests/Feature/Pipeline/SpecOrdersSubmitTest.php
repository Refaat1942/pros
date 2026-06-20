<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordItem;
use App\Models\TechOrderSpec;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class SpecOrdersSubmitTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_spec_create_endpoint_seeds_medical_record_items_when_empty_draft_exists(): void
    {
        $this->stockItem('ITM-003', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $doctor  = $this->userWithRole('doctor');
        $spec    = $this->userWithRole('spec');

        $this->actingAs($doctor);

        $record = MedicalRecord::create([
            'patient_id'     => $patient->id,
            'patient_name'   => $patient->name,
            'patient_type'   => $patient->patient_type,
            'diagnosis'      => 'بتر فوق الركبة',
            'doctor_name'    => $doctor->name,
            'record_date'    => now()->toDateString(),
            'status'         => MedicalRecord::STATUS_DRAFT,
            'locked'         => false,
        ]);

        MedicalRecordItem::create([
            'medical_record_id' => $record->id,
            'stock_item_code'   => 'ITM-003',
            'name'              => 'قدم Carbon Spring',
            'qty'               => 1,
        ]);

        app(\App\Services\MedicalRecordService::class)->lock($record);

        $case = CaseRecord::where('patient_id', $patient->id)->firstOrFail();

        TechOrderSpec::create([
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'doctor_name'  => $doctor->name,
            'locked'       => false,
        ]);

        $response = $this->actingAs($spec)
            ->getJson("/spec/spec/{$case->id}");

        $response->assertOk()
            ->assertJsonPath('draft.items', [])
            ->assertJsonPath('medical_record.items.0.stock_item_code', 'ITM-003');
    }

    public function test_spec_submit_works_with_medical_record_items_after_draft_update(): void
    {
        $this->stockItem('ITM-003', qty: 10);

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $doctor  = $this->userWithRole('doctor');
        $spec    = $this->userWithRole('spec');

        $this->actingAs($doctor);

        $record = MedicalRecord::create([
            'patient_id'     => $patient->id,
            'patient_name'   => $patient->name,
            'patient_type'   => $patient->patient_type,
            'diagnosis'      => 'بتر فوق الركبة',
            'doctor_name'    => $doctor->name,
            'record_date'    => now()->toDateString(),
            'status'         => MedicalRecord::STATUS_DRAFT,
            'locked'         => false,
        ]);

        MedicalRecordItem::create([
            'medical_record_id' => $record->id,
            'stock_item_code'   => 'ITM-003',
            'name'              => 'قدم Carbon Spring',
            'qty'               => 1,
        ]);

        app(\App\Services\MedicalRecordService::class)->lock($record);

        $case = CaseRecord::where('patient_id', $patient->id)->firstOrFail();

        $draft = TechOrderSpec::create([
            'order_ref'    => $case->order_ref,
            'case_id'      => $case->id,
            'patient_name' => $patient->name,
            'company_name' => $case->company_name,
            'doctor_name'  => $doctor->name,
            'locked'       => false,
        ]);

        $this->actingAs($spec);

        $this->putJson("/spec/spec/{$draft->id}", [
            'tech_notes' => 'توصيف من توصيات الطبيب',
            'items'      => [
                ['stock_item_code' => 'ITM-003', 'name' => 'قدم Carbon Spring', 'qty' => 1],
            ],
        ])->assertOk();

        $this->postJson("/spec/spec/{$draft->id}/submit")
            ->assertOk()
            ->assertJsonStructure(['pricing_request' => ['request_no']]);

        $this->assertNotContains($case->id, app(\App\Services\Dashboard\DashboardQueueService::class)->specTechnicalCaseIds());
    }

    public function test_spec_orders_page_wires_submit_script_without_inverted_validation_guard(): void
    {
        $spec = $this->userWithRole('spec');

        $this->actingAs($spec)
            ->get('/spec/orders')
            ->assertOk()
            ->assertSee('btnSubmitSpec', false)
            ->assertSee('spec-dashboard.js', false);

        $js = file_get_contents(public_path('assets/js/pages/spec-dashboard.js'));
        $this->assertStringNotContainsString('!DashboardValidation.validateField', $js);
        $this->assertStringContainsString("axios.post('/spec/spec/' + state.specId + '/submit')", $js);
    }
}
