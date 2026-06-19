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

    public function test_transfer_sets_transferred_to_clinic_at_for_wait_time(): void
    {
        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض وقت الانتظار');

        $appointment = $this->transferPatientToClinicHttp($recep, $patient);

        $this->assertNotNull($appointment->transferred_to_clinic_at);
        $this->assertTrue($appointment->transferred_to_clinic_at->greaterThanOrEqualTo($patient->created_at));
    }
}
