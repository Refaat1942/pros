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
            'transferred_to_clinic'    => true,
            'transferred_to_clinic_at' => Carbon::parse('2026-06-19 10:45:00'),
        ]);
        $appointment->setRelation('patient', $patient);

        $this->assertSame('45 دقائق', $appointment->receptionWaitLabel());
    }

    public function test_format_wait_duration_includes_days_and_hours(): void
    {
        $from = Carbon::parse('2026-06-17 08:00:00');
        $to   = Carbon::parse('2026-06-19 10:30:00');

        $label = Appointment::formatWaitDuration($from, $to);

        $this->assertStringContainsString('2 أيام', $label);
        $this->assertStringContainsString('2 ساعات', $label);
    }
}
