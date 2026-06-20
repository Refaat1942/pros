<?php

namespace Tests\Feature\Patient;

use App\Models\VisitType;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class StorePatientValidationTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_store_patient_rejects_non_numeric_phone_and_national_id(): void
    {
        $user = $this->userWithRole('reception');
        $company = $this->civilianCompany();
        $visitType = VisitType::create(['name' => 'كشف أولي']);

        $response = $this->actingAs($user)->post(route('reception.patients.store'), [
            'form'                => 'patient',
            'name'                => 'مريض تجريبي',
            'phone'               => 'بشيسيسش',
            'national_id'         => 'بشيسيسش',
            'patient_type'        => 'civilian',
            'contract_company_id' => $company->id,
            'visit_type_id'       => $visitType->id,
        ]);

        $response->assertSessionHasErrors(['phone', 'national_id']);
    }
}
