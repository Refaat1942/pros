<?php

namespace Tests\Support;

use App\Models\Appointment;
use App\Models\ContractCompany;
use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Models\User;
use App\Services\Dashboard\DashboardQueueService;

/**
 * Query-Chain assertions — mirrors live dashboard SSR/API queries.
 */
trait DashboardQueueAssertions
{
    protected function queues(): DashboardQueueService
    {
        return app(DashboardQueueService::class);
    }

    protected function transferPatientToClinicHttp(User $reception, Patient $patient): Appointment
    {
        $appointment = Appointment::query()
            ->where('patient_id', $patient->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($reception);
        $this->patchJson("/reception/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_IN_CLINIC,
        ])->assertOk();

        return $appointment->fresh();
    }

    protected function registerCivilianPatientHttp(User $reception, ContractCompany $company, string $name = 'مريض E2E مدني'): Patient
    {
        $this->actingAs($reception);
        $visitType = $this->defaultVisitType();

        $response = $this->postJson('/reception/patients', [
            'name' => $name,
            'patient_type' => Patient::TYPE_CIVILIAN,
            'contract_company_id' => $company->id,
            'visit_type_id' => $visitType->id,
            'phone' => '01011112222',
            'national_id' => '29901010100999',
        ]);

        $response->assertCreated();

        return Patient::where('name', $name)->firstOrFail();
    }

    protected function registerCashPatientHttp(User $reception, string $name = 'مريض نقدي'): Patient
    {
        $this->actingAs($reception);
        $visitType = $this->defaultVisitType();

        $response = $this->postJson('/reception/patients', [
            'name' => $name,
            'patient_type' => Patient::TYPE_CIVILIAN,
            'visit_type_id' => $visitType->id,
            'phone' => '01099998888',
        ]);

        $response->assertCreated();

        return Patient::where('name', $name)->firstOrFail();
    }

    protected function registerMilitaryPatientHttp(
        User $reception,
        ContractCompany $company,
        MilitaryRank $rank,
        string $name = 'مريض E2E عسكري',
    ): Patient {
        $this->actingAs($reception);
        $visitType = $this->defaultVisitType();

        $response = $this->postJson('/reception/patients', [
            'name' => $name,
            'patient_type' => Patient::TYPE_MILITARY,
            'contract_company_id' => $company->id,
            'military_rank_id' => $rank->id,
            'military_number' => 'MIL-'.substr(md5($name), 0, 6),
            'seniority_number' => 'SEN-'.substr(md5($name), 6, 6),
            'military_weapon' => 'المشاة',
            'visit_type_id' => $visitType->id,
            'sovereign_entity' => 'القوات المسلحة',
            'phone' => '01122223333',
        ]);

        $response->assertCreated();

        return Patient::where('name', $name)->firstOrFail();
    }
}
