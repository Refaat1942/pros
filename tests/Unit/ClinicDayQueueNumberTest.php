<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Patient;
use App\Services\PatientService;
use App\Support\ClinicTime;
use Carbon\Carbon;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class ClinicDayQueueNumberTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_first_patient_of_clinic_day_gets_queue_number_one(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 09:00:00', ClinicTime::zone()));

        $patient = app(PatientService::class)->register([
            'name'          => 'مريض أول',
            'patient_type'  => Patient::TYPE_CIVILIAN,
            'visit_type_id' => $this->defaultVisitType()->id,
        ]);

        $appointment = Appointment::where('patient_id', $patient->id)->first();

        $this->assertSame(1, $appointment->queue_number);
        $this->assertSame('2026-06-30', $appointment->clinic_day->toDateString());

        Carbon::setTestNow();
    }

    public function test_queue_number_increments_within_same_clinic_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 11:00:00', ClinicTime::zone()));

        $service = app(PatientService::class);
        $visitTypeId = $this->defaultVisitType()->id;

        $service->register([
            'name'          => 'مريض 1',
            'patient_type'  => Patient::TYPE_CIVILIAN,
            'visit_type_id' => $visitTypeId,
        ]);

        $second = $service->register([
            'name'          => 'مريض 2',
            'patient_type'  => Patient::TYPE_CIVILIAN,
            'visit_type_id' => $visitTypeId,
        ]);

        $this->assertSame(2, Appointment::where('patient_id', $second->id)->value('queue_number'));

        Carbon::setTestNow();
    }

    public function test_queue_resets_after_one_am_clinic_day_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 23:00:00', ClinicTime::zone()));

        $service = app(PatientService::class);
        $visitTypeId = $this->defaultVisitType()->id;

        $service->register([
            'name'          => 'مريض مساء',
            'patient_type'  => Patient::TYPE_CIVILIAN,
            'visit_type_id' => $visitTypeId,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-07-01 00:30:00', ClinicTime::zone()));

        $lateNight = $service->register([
            'name'          => 'مريض قبل الفجر',
            'patient_type'  => Patient::TYPE_CIVILIAN,
            'visit_type_id' => $visitTypeId,
        ]);

        $lateAppt = Appointment::where('patient_id', $lateNight->id)->first();
        $this->assertSame('2026-06-30', $lateAppt->clinic_day->toDateString());
        $this->assertSame(2, $lateAppt->queue_number);

        Carbon::setTestNow(Carbon::parse('2026-07-01 01:05:00', ClinicTime::zone()));

        $newDay = $service->register([
            'name'          => 'مريض يوم جديد',
            'patient_type'  => Patient::TYPE_CIVILIAN,
            'visit_type_id' => $visitTypeId,
        ]);

        $newAppt = Appointment::where('patient_id', $newDay->id)->first();
        $this->assertSame('2026-07-01', $newAppt->clinic_day->toDateString());
        $this->assertSame(1, $newAppt->queue_number);

        Carbon::setTestNow();
    }
}
