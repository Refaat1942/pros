<?php

namespace Tests\Feature\Patient;

use App\Models\CaseRecord;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ReceptionSelfServiceLookupTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_reception_can_lookup_patient_by_phone_with_journey(): void
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $patient->update([
            'phone' => '01066666666',
            'tracking_uid' => 'case-phone6666',
        ]);

        $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING);

        $reception = $this->userWithRole('reception');

        $response = $this->actingAs($reception)->getJson(
            '/reception/selfservice/lookup?q=01066666666'
        );

        $response->assertOk();
        $response->assertJsonPath('patient.phone', '01066666666');
        $response->assertJsonPath('patient.name', $patient->name);
        $response->assertJsonPath('active_case.stage_key', CaseRecord::STAGE_MANUFACTURING);
        $response->assertJsonStructure([
            'patient',
            'active_case',
            'cases',
            'tracking' => ['steps', 'stage_label', 'pathway'],
            'progress_percent',
            'expected_delivery',
        ]);
        $response->assertJsonFragment(['label' => 'قسم الإنتاج — تصنيع']);
    }

    public function test_lookup_returns_404_for_unknown_phone(): void
    {
        $reception = $this->userWithRole('reception');

        $this->actingAs($reception)
            ->getJson('/reception/selfservice/lookup?q=01999999999')
            ->assertNotFound();
    }

    public function test_reception_can_lookup_patient_by_name(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $patient->update(['name' => 'مريض بحث خدمة ذاتية']);

        $reception = $this->userWithRole('reception');

        $this->actingAs($reception)
            ->getJson('/reception/selfservice/lookup?q=بحث خدمة')
            ->assertOk()
            ->assertJsonPath('patient.name', 'مريض بحث خدمة ذاتية');
    }

    public function test_reception_can_lookup_patient_by_work_order_no(): void
    {
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING);
        $case->update(['work_order_no' => 'WO-SS-9001']);

        $reception = $this->userWithRole('reception');

        $this->actingAs($reception)
            ->getJson('/reception/selfservice/lookup?q=WO-SS-9001')
            ->assertOk()
            ->assertJsonPath('active_case.work_order_no', 'WO-SS-9001');
    }
}
