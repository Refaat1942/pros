<?php

namespace Tests\Feature\Reception;

use App\Models\Appointment;
use App\Models\Patient;
use App\Support\ClinicTime;
use Carbon\Carbon;
use Tests\Support\DashboardQueueAssertions;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class PatientRegistrationTimezoneTest extends TestCase
{
    use DashboardQueueAssertions;
    use ProstheticTestHelper;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_patient_registered_after_midnight_cairo_uses_today_date(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        // ٢٧/٠٦/٢٠٢٦ ٠٠:١١ صباحاً بتوقيت مصر
        Carbon::setTestNow('2026-06-26 21:11:00');

        $company = $this->civilianCompany();
        $recep   = $this->userWithRole('reception');

        $patient = $this->registerCivilianPatientHttp($recep, $company, 'مريض بعد منتصف الليل');

        $patient->refresh();

        $this->assertSame('2026-06-27', $patient->registered_at->toDateString());
        $appointment = Appointment::where('patient_id', $patient->id)->firstOrFail();

        $this->assertSame('2026-06-27', $appointment->appointment_date->toDateString());
        $this->assertStringContainsString('27/06/2026', $appointment->registeredAtFormatted());
        $this->assertStringContainsString('00:11', $appointment->registeredAtFormatted());

        $this->actingAs($recep)
            ->getJson('/reception/appointments/list?date=' . ClinicTime::todayDateString())
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.patient_name', 'مريض بعد منتصف الليل');
    }
}
