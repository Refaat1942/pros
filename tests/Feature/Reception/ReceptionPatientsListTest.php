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
}
