<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Services\PatientService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class PatientCodeGenerationTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_register_generates_six_digit_patient_code_and_queue_number(): void
    {
        $company = $this->civilianCompany();
        $visitType = $this->defaultVisitType();
        $reception = $this->userWithRole('reception');

        $patient = app(PatientService::class)->register([
            'name' => 'مريض اختبار',
            'phone' => '01012345678',
            'national_id' => '29901010100001',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'visit_type_id' => $visitType->id,
        ]);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $patient->patient_code);
        $this->assertSame('QR-'.$patient->patient_code, $patient->patient_qr);

        $this->actingAs($reception)
            ->getJson("/reception/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('queue_number', 1);
    }

    public function test_register_assigns_sequential_patient_serial(): void
    {
        $company = $this->civilianCompany();
        $visitType = $this->defaultVisitType();
        $year = now()->year;

        $first = app(PatientService::class)->register([
            'name' => 'مريض أول',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'visit_type_id' => $visitType->id,
        ]);

        $second = app(PatientService::class)->register([
            'name' => 'مريض ثانٍ',
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'visit_type_id' => $visitType->id,
        ]);

        $this->assertSame("PT-{$year}-0001", $first->patient_serial);
        $this->assertSame("PT-{$year}-0002", $second->patient_serial);
    }
}
