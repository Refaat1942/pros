<?php

namespace Tests\Feature\Reception;

use App\Support\ClinicTime;
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
            ->getJson('/reception/appointments/list?date=' . ClinicTime::todayDateString())
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
        $date    = ClinicTime::todayDateString();

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
            ->assertJsonPath('data.0.patient_name', 'مريض عسكري مواعيد')
            ->assertJsonPath('data.0.patient_type', 'military')
            ->assertJsonPath('data.0.patient_type_label', 'عسكري');
    }

    public function test_reception_wait_label_after_five_minutes_before_transfer(): void
    {
        \Carbon\Carbon::setTestNow('2026-06-26 10:00:00');

        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');
        $this->registerCivilianPatientHttp($recep, $company, 'انتظار خمس دقائق');

        \Carbon\Carbon::setTestNow('2026-06-26 10:05:00');

        $this->actingAs($recep)
            ->getJson('/reception/appointments/list?date=2026-06-26')
            ->assertOk()
            ->assertJsonPath('data.0.wait_label', '٥ دقائق');

        \Carbon\Carbon::setTestNow();
    }

    public function test_reception_wait_label_after_transfer_records_elapsed_minutes(): void
    {
        \Carbon\Carbon::setTestNow('2026-06-26 11:00:00');

        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');
        $patient = $this->registerCivilianPatientHttp($recep, $company, 'تحويل بعد ثلاث دقائق');

        \Carbon\Carbon::setTestNow('2026-06-26 11:03:00');
        $this->transferPatientToClinicHttp($recep, $patient);

        $this->actingAs($recep)
            ->getJson('/reception/appointments/list?date=2026-06-26')
            ->assertOk()
            ->assertJsonPath('data.0.wait_label', '٣ دقائق');

        \Carbon\Carbon::setTestNow();
    }
}
