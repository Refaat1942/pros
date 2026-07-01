<?php

namespace Tests\Feature\Pipeline;

use App\Models\Appointment;
use App\Models\Patient;
use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class DoctorQueueStatsTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    public function test_examined_count_increments_after_report_approval(): void
    {
        $company  = $this->civilianCompany();
        $recep    = $this->userWithRole('reception');
        $doctor   = $this->userWithRole('doctor');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض عداد الفحص');
        $this->transferPatientToClinicHttp($recep, $patient);

        $beforeQueue = $this->actingAs($doctor)->get('/doctor/queue');
        $beforeQueue->assertOk()
            ->assertViewHas('queue_waiting_count', 1)
            ->assertViewHas('queue_examined_count', 0);

        $appointmentId = Appointment::where('patient_id', $patient->id)->value('id');

        $this->actingAs($doctor)->postJson('/doctor/diagnosis', [
            'patient_id'     => $patient->id,
            'appointment_id' => $appointmentId,
            'diagnosis'      => 'تشخيص اختبار العداد',
            'lock'           => true,
        ])->assertCreated();

        $afterQueue = $this->actingAs($doctor)->get('/doctor/queue');
        $afterQueue->assertOk()
            ->assertViewHas('queue_waiting_count', 0)
            ->assertViewHas('queue_examined_count', 1)
            ->assertViewHas('queue_today_total', 1);
    }

    public function test_reception_pending_count_shows_untransferred_patients(): void
    {
        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');
        $doctor  = $this->userWithRole('doctor');

        $waiting = $this->registerCivilianPatientHttp($recep, $company, 'مريض بالاستقبال');
        $transferred = $this->registerCivilianPatientHttp($recep, $company, 'مريض محوّل');
        $this->transferPatientToClinicHttp($recep, $transferred);

        $this->actingAs($doctor)
            ->get('/doctor/queue')
            ->assertOk()
            ->assertViewHas('queue_reception_pending_count', 1)
            ->assertViewHas('queue_waiting_count', 1)
            ->assertSee('id="receptionPendingCount"', false)
            ->assertSee('في الاستقبال — لم يُحوَّلوا');

        $this->transferPatientToClinicHttp($recep, $waiting);

        $this->actingAs($doctor)
            ->get('/doctor/queue')
            ->assertOk()
            ->assertViewHas('queue_reception_pending_count', 0)
            ->assertViewHas('queue_waiting_count', 2);
    }

    public function test_sidebar_shows_waiting_count_beside_queue_link(): void
    {
        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');
        $doctor  = $this->userWithRole('doctor');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض شارة القائمة');
        $this->transferPatientToClinicHttp($recep, $patient);

        $this->actingAs($doctor)
            ->get('/doctor/queue')
            ->assertOk()
            ->assertSee('id="sidebarQueueBadge"', false)
            ->assertSee('>1</span>', false);

        $appointmentId = Appointment::where('patient_id', $patient->id)->value('id');

        $this->actingAs($doctor)->postJson('/doctor/diagnosis', [
            'patient_id'     => $patient->id,
            'appointment_id' => $appointmentId,
            'diagnosis'      => 'تشخيص',
            'lock'           => true,
        ])->assertCreated();

        $this->actingAs($doctor)
            ->get('/doctor/records')
            ->assertOk()
            ->assertSee('id="sidebarQueueBadge"', false)
            ->assertSee('>0</span>', false);
    }

    public function test_transfer_sets_transferred_to_clinic_at_for_wait_time(): void
    {
        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض وقت الانتظار');

        $appointment = $this->transferPatientToClinicHttp($recep, $patient);

        $this->assertNotNull($appointment->transferred_to_clinic_at);
        $this->assertTrue($appointment->transferred_to_clinic_at->greaterThanOrEqualTo($patient->created_at));
    }

    public function test_doctor_queue_shows_contract_entity_for_civilian_patient(): void
    {
        $company = $this->civilianCompany('التأمين الصحي');
        $recep   = $this->userWithRole('reception');
        $doctor  = $this->userWithRole('doctor');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'احمد عرفه');
        $this->transferPatientToClinicHttp($recep, $patient);

        $this->actingAs($doctor)
            ->get('/doctor/queue')
            ->assertOk()
            ->assertSee('التأمين الصحي', false)
            ->assertSee('متعاقد', false);
    }
}
