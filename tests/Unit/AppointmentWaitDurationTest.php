<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\Carbon;
use Tests\TestCase;

class AppointmentWaitDurationTest extends TestCase
{
    public function test_reception_wait_label_from_patient_creation_to_transfer(): void
    {
        $patient = new Patient([
            'name' => 'اختبار',
        ]);
        $patient->created_at = Carbon::parse('2026-06-19 10:00:00');

        $appointment = new Appointment([
            'transferred_to_clinic' => true,
            'transferred_to_clinic_at' => Carbon::parse('2026-06-19 10:45:00'),
        ]);
        $appointment->setRelation('patient', $patient);

        $this->assertSame('٤٥ دقائق', $appointment->receptionWaitLabel());
    }

    public function test_reception_wait_label_shows_less_than_one_minute_when_immediate(): void
    {
        $patient = new Patient(['name' => 'اختبار']);
        $patient->created_at = Carbon::parse('2026-06-19 10:00:00');

        $appointment = new Appointment([
            'transferred_to_clinic' => true,
            'transferred_to_clinic_at' => Carbon::parse('2026-06-19 10:00:30'),
        ]);
        $appointment->setRelation('patient', $patient);

        $this->assertSame('أقل من دقيقة', $appointment->receptionWaitLabel());
    }

    public function test_clinic_wait_label_from_transfer_until_now(): void
    {
        $appointment = new Appointment([
            'transferred_to_clinic' => true,
            'transferred_to_clinic_at' => Carbon::parse('2026-06-19 10:00:00'),
        ]);

        $label = $appointment->clinicWaitLabel(Carbon::parse('2026-06-19 10:03:00'));

        $this->assertSame('٣ دقائق', $label);
    }

    public function test_clinic_wait_label_shows_less_than_one_minute_when_under_sixty_seconds(): void
    {
        $appointment = new Appointment([
            'transferred_to_clinic' => true,
            'transferred_to_clinic_at' => Carbon::parse('2026-06-19 10:00:00'),
        ]);

        $label = $appointment->clinicWaitLabel(Carbon::parse('2026-06-19 10:00:45'));

        $this->assertSame('أقل من دقيقة', $label);
    }

    public function test_reception_desk_wait_label_from_registration_until_now(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        $patient = new Patient(['name' => 'اختبار']);
        $patient->created_at = Carbon::parse('2026-06-26 08:00:00', 'UTC');

        $appointment = new Appointment(['transferred_to_clinic' => false]);
        $appointment->setRelation('patient', $patient);

        $label = $appointment->receptionDeskWaitLabel(Carbon::parse('2026-06-26 08:03:00', 'UTC'));

        $this->assertSame('٣ دقائق', $label);
    }

    public function test_reception_desk_registered_at_uses_clinic_timezone(): void
    {
        config(['app.timezone' => 'UTC', 'app.clinic_timezone' => 'Africa/Cairo']);

        $patient = new Patient(['name' => 'اختبار']);
        $patient->created_at = Carbon::parse('2026-06-26 08:19:00', 'UTC');

        $appointment = new Appointment;
        $appointment->setRelation('patient', $patient);

        $this->assertStringContainsString('11:19', $appointment->registeredAtFormatted());
    }

    public function test_format_wait_duration_includes_days_and_hours(): void
    {
        $from = Carbon::parse('2026-06-17 08:00:00');
        $to = Carbon::parse('2026-06-19 10:30:00');

        $label = Appointment::formatWaitDuration($from, $to);

        $this->assertStringContainsString('٢ أيام', $label);
        $this->assertStringContainsString('٢ ساعات', $label);
    }
}
