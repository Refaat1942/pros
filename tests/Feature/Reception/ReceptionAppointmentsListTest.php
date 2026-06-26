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
                    0 => ['registered_at_formatted', 'wait_label', 'patient_name'],
                ],
            ])
            ->assertJsonPath('data.0.patient_name', 'مريض توقيت الاستقبال');
    }
}
