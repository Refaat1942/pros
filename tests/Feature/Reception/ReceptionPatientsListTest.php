<?php

namespace Tests\Feature\Reception;

use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ReceptionPatientsListTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    public function test_patients_list_search_by_queue_number(): void
    {
        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض رقم الدور');

        $this->actingAs($recep)
            ->getJson('/reception/patients/list?search=' . $patient->id)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $patient->id)
            ->assertJsonPath('data.0.name', 'مريض رقم الدور');
    }

    public function test_patients_list_filters_by_patient_type(): void
    {
        $company = $this->civilianCompany();
        $milCo   = $this->militaryCompany();
        $rank    = \App\Models\MilitaryRank::create(['name' => 'نقيب', 'rank_code' => 'CAP', 'sort_order' => 1]);
        $recep   = $this->userWithRole('reception');

        $this->registerCivilianPatientHttp($recep, $company, 'مريض مدني سجل');
        $this->registerMilitaryPatientHttp($recep, $milCo, $rank, 'مريض عسكري سجل');

        $this->actingAs($recep)
            ->getJson('/reception/patients/list?patient_type=civilian')
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('total', 1)
                ->where('data.0.name', 'مريض مدني سجل')
                ->etc());

        $this->actingAs($recep)
            ->getJson('/reception/patients/list?patient_type=military')
            ->assertOk()
            ->assertJson(fn ($json) => $json
                ->where('total', 1)
                ->where('data.0.name', 'مريض عسكري سجل')
                ->etc());
    }
}
