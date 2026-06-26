<?php

namespace Tests\Feature\Reception;

use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ReceptionAppointmentsListTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    public function test_appointments_list_includes_registration_and_wait_labels(): void
    {
        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض توقيت الاستقبال');

        $this->actingAs($recep)
            ->getJson('/reception/appointments/list?date=' . now()->toDateString())
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonStructure([
                'data' => [
                    0 => ['registered_at_formatted', 'wait_label', 'patient_name', 'queue_number'],
                ],
            ])
            ->assertJsonPath('data.0.patient_name', 'مريض توقيت الاستقبال')
            ->assertJsonPath('data.0.queue_number', $patient->id);
    }

    public function test_appointments_list_filters_by_patient_type(): void
    {
        $company = $this->civilianCompany();
        $milCo   = $this->militaryCompany();
        $rank    = \App\Models\MilitaryRank::create(['name' => 'نقيب', 'rank_code' => 'CAP', 'sort_order' => 1]);
        $recep   = $this->userWithRole('reception');
        $date    = now()->toDateString();

        $this->registerCivilianPatientHttp($recep, $company, 'مريض مدني مواعيد');
        $this->registerMilitaryPatientHttp($recep, $milCo, $rank, 'مريض عسكري مواعيد');

        $this->actingAs($recep)
            ->getJson('/reception/appointments/list?date=' . $date . '&patient_type=civilian')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.patient_name', 'مريض مدني مواعيد');

        $this->actingAs($recep)
            ->getJson('/reception/appointments/list?date=' . $date . '&patient_type=military')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.patient_name', 'مريض عسكري مواعيد');
    }
}
