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
        $response->assertJsonFragment(['label' => 'التصنيع بالورشة']);
    }

    public function test_lookup_returns_404_for_unknown_phone(): void
    {
        $reception = $this->userWithRole('reception');

        $this->actingAs($reception)
            ->getJson('/reception/selfservice/lookup?q=01999999999')
            ->assertNotFound();
    }
}
