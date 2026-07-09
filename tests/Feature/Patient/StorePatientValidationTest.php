<?php

namespace Tests\Feature\Patient;

use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Models\VisitType;
use App\Support\PatientEntityPresenter;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class StorePatientValidationTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_store_civilian_patient_without_phone(): void
    {
        $user = $this->userWithRole('reception');
        $company = $this->civilianCompany();
        $visitType = VisitType::create(['name' => 'كشف أولي']);

        $response = $this->actingAs($user)->post(route('reception.patients.store'), [
            'form' => 'patient',
            'name' => 'مريض بدون هاتف',
            'patient_type' => 'civilian',
            'contract_company_id' => $company->id,
            'visit_type_id' => $visitType->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $patient = Patient::query()->where('name', 'مريض بدون هاتف')->first();
        $this->assertNotNull($patient);
        $this->assertNull($patient->phone);
    }

    public function test_store_patient_rejects_non_numeric_phone_and_national_id(): void
    {
        $user = $this->userWithRole('reception');
        $company = $this->civilianCompany();
        $visitType = VisitType::create(['name' => 'كشف أولي']);

        $response = $this->actingAs($user)->post(route('reception.patients.store'), [
            'form' => 'patient',
            'name' => 'مريض تجريبي',
            'phone' => 'بشيسيسش',
            'national_id' => 'بشيسيسش',
            'patient_type' => 'civilian',
            'contract_company_id' => $company->id,
            'visit_type_id' => $visitType->id,
        ]);

        $response->assertSessionHasErrors(['phone', 'national_id']);
    }

    public function test_store_civilian_cash_patient_without_company(): void
    {
        $user = $this->userWithRole('reception');
        $visitType = VisitType::create(['name' => 'كشف نقدي']);

        $response = $this->actingAs($user)->post(route('reception.patients.store'), [
            'form' => 'patient',
            'name' => 'مريض نقدي',
            'patient_type' => 'civilian',
            'visit_type_id' => $visitType->id,
        ]);

        $response->assertSessionHasNoErrors();

        $patient = Patient::query()->where('name', 'مريض نقدي')->first();
        $this->assertNotNull($patient);
        $this->assertNull($patient->contract_company_id);
        $this->assertSame('نقدي', $patient->displayEntity());
        $this->assertSame(PatientEntityPresenter::KIND_CASH, $patient->entityPresentation()['kind']);
    }

    public function test_store_military_patient_without_company_or_sovereign_input(): void
    {
        $user = $this->userWithRole('reception');
        $rank = MilitaryRank::create(['name' => 'نقيب', 'rank_code' => 'CAP', 'sort_order' => 1]);
        $visitType = VisitType::create(['name' => 'كشف أولي']);

        $response = $this->actingAs($user)->post(route('reception.patients.store'), [
            'form' => 'patient',
            'name' => 'النقيب أحمد عسكري',
            'phone' => '01012345678',
            'national_id' => '29901010100002',
            'patient_type' => Patient::TYPE_MILITARY,
            'military_rank_id' => $rank->id,
            'military_number' => 'MIL-12345',
            'seniority_number' => 'SEN-9876',
            'military_weapon' => 'المشاة',
            'visit_type_id' => $visitType->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $patient = Patient::query()->where('name', 'النقيب أحمد عسكري')->first();
        $this->assertNotNull($patient);
        $this->assertSame(Patient::TYPE_MILITARY, $patient->patient_type);
        $this->assertSame(Patient::MILITARY_SOVEREIGN_ENTITY, $patient->sovereign_entity);
        $this->assertNull($patient->contract_company_id);
        $this->assertNull($patient->company_name);
        $this->assertSame('نقيب', $patient->rank);
        $this->assertSame('MIL-12345', $patient->military_number);
        $this->assertSame('SEN-9876', $patient->seniority_number);
        $this->assertSame('المشاة', $patient->military_weapon);
    }

    public function test_store_military_patient_requires_weapon_and_military_numbers(): void
    {
        $user = $this->userWithRole('reception');
        $rank = MilitaryRank::create(['name' => 'رائد', 'rank_code' => 'MAJ', 'sort_order' => 2]);
        $visitType = VisitType::create(['name' => 'كشف عسكري']);

        $response = $this->actingAs($user)->post(route('reception.patients.store'), [
            'form' => 'patient',
            'name' => 'ضابط بدون بيانات',
            'patient_classification' => 'military',
            'military_rank_id' => $rank->id,
            'visit_type_id' => $visitType->id,
        ]);

        $response->assertSessionHasErrors(['military_number', 'seniority_number', 'military_weapon']);
    }
}
